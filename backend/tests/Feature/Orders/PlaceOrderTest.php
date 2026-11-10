<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\ReservationConflict;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Orders\ReserveTickets;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

beforeEach(function () {
    $this->attendee = User::factory()->create();
    $this->event = Event::factory()->create();
    $this->pool = TicketType::factory()->for($this->event)->create(['price' => 12000, 'quantity' => 10]);
    $this->actingAs($this->attendee);
});

/**
 * @return array{eventId: int, items: list<array{ticketTypeId: int, quantity: int}>}
 */
function orderBody(TicketType $pool, int $quantity = 2): array
{
    return ['eventId' => $pool->event_id, 'items' => [['ticketTypeId' => $pool->id, 'quantity' => $quantity]]];
}

/**
 * An order of the given status with tickets in the pool, without touching the pool counters.
 */
function existingOrder(User $user, TicketType $pool, int $quantity, OrderStatus $status = OrderStatus::Pending): Order
{
    $order = Order::factory()->for($user)->create(['event_id' => $pool->event_id, 'status' => $status]);

    OrderItem::factory()->for($order)->create(['ticket_type_id' => $pool->id, 'quantity' => $quantity]);

    return $order;
}

it('creates a pending order that holds the seats', function () {
    $this->freezeTime();

    $response = $this->postJson('/api/orders', orderBody($this->pool, 2))
        ->assertCreated()
        ->assertJsonPath('status', 'PENDING')
        ->assertJsonPath('total', 24000)
        ->assertJsonPath('paymentStatus', null)
        ->assertJsonPath('event.id', $this->event->id)
        ->assertJsonPath('event.title', $this->event->title)
        ->assertJsonPath('items.0.ticketTypeId', $this->pool->id)
        ->assertJsonPath('items.0.name', $this->pool->name)
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.unitPrice', 12000);

    $order = Order::findOrFail($response->json('id'));

    expect($order->user_id)->toBe($this->attendee->id)
        ->and($order->event_id)->toBe($this->event->id)
        ->and($order->expires_at->toDateTimeString())->toBe(now()->addMinutes(15)->toDateTimeString())
        ->and($response->json('expiresAt'))->toBe(now()->addMinutes(15)->utc()->format('Y-m-d\TH:i:s\Z'))
        ->and($this->pool->fresh())
        ->held->toBe(2)
        ->sold->toBe(0)
        ->version->toBe(1);
});

it('holds seats in several pools of one event', function () {
    $second = TicketType::factory()->for($this->event)->create(['price' => 5000, 'quantity' => 10]);

    $this->postJson('/api/orders', [
        'eventId' => $this->event->id,
        'items' => [
            ['ticketTypeId' => $second->id, 'quantity' => 1],
            ['ticketTypeId' => $this->pool->id, 'quantity' => 3],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('total', 41000)
        ->assertJsonCount(2, 'items');

    expect($this->pool->fresh()->held)->toBe(3)
        ->and($second->fresh()->held)->toBe(1);
});

it('updates pools in ascending id order whatever the request order', function () {
    $second = TicketType::factory()->for($this->event)->create();
    $holdingAfterEachUpdate = [];

    DB::listen(function (QueryExecuted $query) use (&$holdingAfterEachUpdate) {
        if (str_starts_with($query->sql, 'update `ticket_types`')) {
            $holdingAfterEachUpdate[] = DB::table('ticket_types')->where('held', '>', 0)->orderBy('id')->pluck('id')->all();
        }
    });

    $this->postJson('/api/orders', [
        'eventId' => $this->event->id,
        'items' => [
            ['ticketTypeId' => $second->id, 'quantity' => 1],
            ['ticketTypeId' => $this->pool->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect($holdingAfterEachUpdate)->toBe([[$this->pool->id], [$this->pool->id, $second->id]]);
});

it('takes the last free seats', function () {
    $this->pool->forceFill(['sold' => 6, 'held' => 2])->save();

    $this->postJson('/api/orders', orderBody($this->pool, 2))->assertCreated();

    expect($this->pool->fresh()->held)->toBe(4);
});

it('answers 409 when the pool is sold out', function () {
    $pool = TicketType::factory()->for($this->event)->soldOut()->create();

    $this->postJson('/api/orders', orderBody($pool, 1))
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKETS_SOLD_OUT');

    expect(Order::count())->toBe(0);
});

it('answers 409 when fewer seats are free than requested', function () {
    $this->pool->forceFill(['sold' => 6, 'held' => 3])->save();

    $this->postJson('/api/orders', orderBody($this->pool, 2))
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKETS_SOLD_OUT');

    expect($this->pool->fresh())->held->toBe(3)->version->toBe(0);
});

it('leaves the first pool untouched when the second one is sold out', function () {
    $soldOut = TicketType::factory()->for($this->event)->soldOut()->create();

    $this->postJson('/api/orders', [
        'eventId' => $this->event->id,
        'items' => [
            ['ticketTypeId' => $this->pool->id, 'quantity' => 1],
            ['ticketTypeId' => $soldOut->id, 'quantity' => 1],
        ],
    ])->assertConflict();

    expect($this->pool->fresh())->held->toBe(0)->version->toBe(0)
        ->and(Order::count())->toBe(0);
});

it('allows up to six tickets per person for an event', function () {
    existingOrder($this->attendee, $this->pool, 4);

    $this->postJson('/api/orders', orderBody($this->pool, 2))->assertCreated();
});

it('answers 409 above six tickets per person', function (int $active, int $requested) {
    if ($active > 0) {
        existingOrder($this->attendee, $this->pool, $active);
    }

    $this->postJson('/api/orders', orderBody($this->pool, $requested))
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');

    expect($this->pool->fresh()->held)->toBe(0);
})->with([
    'one order' => [0, 7],
    'with an active order' => [4, 3],
]);

it('counts tickets of pending and paid orders towards the limit', function (OrderStatus $status) {
    existingOrder($this->attendee, $this->pool, 6, $status);

    $this->postJson('/api/orders', orderBody($this->pool, 1))
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');
})->with([OrderStatus::Pending, OrderStatus::Paid]);

it('does not count tickets of inactive orders towards the limit', function (OrderStatus $status) {
    existingOrder($this->attendee, $this->pool, 6, $status);

    $this->postJson('/api/orders', orderBody($this->pool, 1))->assertCreated();
})->with([OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled, OrderStatus::Refunded]);

it('does not count tickets of other people or other events towards the limit', function () {
    existingOrder(User::factory()->create(), $this->pool, 6);
    existingOrder($this->attendee, TicketType::factory()->create(), 6);

    $this->postJson('/api/orders', orderBody($this->pool, 6))->assertCreated();
});

it('sums all items of the request against the limit', function () {
    $second = TicketType::factory()->for($this->event)->create();

    $this->postJson('/api/orders', [
        'eventId' => $this->event->id,
        'items' => [
            ['ticketTypeId' => $this->pool->id, 'quantity' => 4],
            ['ticketTypeId' => $second->id, 'quantity' => 3],
        ],
    ])
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');
});

it('answers 409 outside the sale window', function (Event $event) {
    $pool = TicketType::factory()->for($event)->create();

    $this->postJson('/api/orders', orderBody($pool))
        ->assertConflict()
        ->assertJsonPath('error.code', 'SALES_CLOSED');
})->with([
    'draft' => fn () => Event::factory()->draft()->create(),
    'cancelled' => fn () => Event::factory()->cancelled()->create(),
    'finished' => fn () => Event::factory()->finished()->create(),
    'already started' => fn () => Event::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]),
]);

it('stops selling at the exact start of the event', function () {
    $this->freezeTime();

    $event = Event::factory()->create(['starts_at' => now(), 'ends_at' => now()->addHour()]);
    $pool = TicketType::factory()->for($event)->create();

    $this->postJson('/api/orders', orderBody($pool))->assertConflict();
});

it('requires authentication', function () {
    auth()->forgetGuards();

    $this->postJson('/api/orders', orderBody($this->pool))->assertUnauthorized();
});

it('is closed to other roles', function (User $user) {
    $this->actingAs($user);

    $this->postJson('/api/orders', orderBody($this->pool))->assertForbidden();
})->with([
    'organizer' => fn () => User::factory()->organizer()->create(),
    'admin' => fn () => User::factory()->admin()->create(),
]);

it('rejects an invalid body with 400', function (array $body, string $field) {
    $this->postJson('/api/orders', $body)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    'no event' => [['items' => [['ticketTypeId' => 1, 'quantity' => 1]]], 'eventId'],
    'no items' => [['eventId' => 1], 'items'],
    'empty items' => [['eventId' => 1, 'items' => []], 'items'],
    'no ticket type' => [['eventId' => 1, 'items' => [['quantity' => 1]]], 'items.0.ticketTypeId'],
    'no quantity' => [['eventId' => 1, 'items' => [['ticketTypeId' => 1]]], 'items.0.quantity'],
    'zero quantity' => [['eventId' => 1, 'items' => [['ticketTypeId' => 1, 'quantity' => 0]]], 'items.0.quantity'],
    'text quantity' => [['eventId' => 1, 'items' => [['ticketTypeId' => 1, 'quantity' => 'two']]], 'items.0.quantity'],
    'repeated ticket type' => [['eventId' => 1, 'items' => [['ticketTypeId' => 1, 'quantity' => 1], ['ticketTypeId' => 1, 'quantity' => 1]]], 'items.0.ticketTypeId'],
]);

it('rejects identity fields in the body with 400', function (string $field) {
    $this->postJson('/api/orders', [...orderBody($this->pool), $field => 1])
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', $field);

    expect(Order::count())->toBe(0);
})->with(['user_id', 'userId']);

it('answers 404 for an unknown event', function () {
    $this->postJson('/api/orders', [...orderBody($this->pool), 'eventId' => 999999])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('answers 404 for a ticket type of another event', function () {
    $foreign = TicketType::factory()->create();

    $this->postJson('/api/orders', [
        'eventId' => $this->event->id,
        'items' => [
            ['ticketTypeId' => $this->pool->id, 'quantity' => 1],
            ['ticketTypeId' => $foreign->id, 'quantity' => 1],
        ],
    ])->assertNotFound();

    expect($this->pool->fresh()->held)->toBe(0);
});

it('checks in the order 401, 403 role, 400, 404, 409', function () {
    $missing = ['eventId' => 999999, 'items' => [['ticketTypeId' => 999999, 'quantity' => 1]]];
    $soldOut = TicketType::factory()->for($this->event)->soldOut()->create();

    auth()->forgetGuards();

    $this->postJson('/api/orders', [])->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->postJson('/api/orders', [])->assertForbidden();

    $this->actingAs($this->attendee);
    $this->postJson('/api/orders', [])->assertStatus(400);
    $this->postJson('/api/orders', $missing)->assertNotFound();
    $this->postJson('/api/orders', orderBody($soldOut, 1))->assertConflict();
});

it('waits between 10 and 50 ms before a retry', function () {
    Sleep::fake();

    $attempts = 0;

    TicketType::retrieved(function (TicketType $ticketType) use (&$attempts) {
        if ($attempts++ === 0) {
            DB::table('ticket_types')->where('id', $ticketType->id)->increment('version');
        }
    });

    app(ReserveTickets::class)->handle($this->attendee, $this->event->id, [['ticketTypeId' => $this->pool->id, 'quantity' => 1]]);

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 10 && $duration->totalMilliseconds <= 50);
});

it('stops after the configured number of attempts', function () {
    Sleep::fake();
    config(['ticketflow.reservation_attempts' => 3]);

    TicketType::retrieved(function (TicketType $ticketType) {
        DB::table('ticket_types')->where('id', $ticketType->id)->increment('version');
    });

    expect(fn () => app(ReserveTickets::class)->handle($this->attendee, $this->event->id, [['ticketTypeId' => $this->pool->id, 'quantity' => 1]]))
        ->toThrow(ReservationConflict::class);

    Sleep::assertSleptTimes(2);
});
