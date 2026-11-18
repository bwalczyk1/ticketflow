<?php

use App\Enums\EventStatus;
use App\Enums\WaitlistStatus;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Models\Event as EventModel;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\OrderFixtures;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->travelTo('2026-11-14 12:00:00');

    $this->attendee = User::factory()->create();
    $this->event = EventModel::factory()->create(['title' => 'Jazz w Piwnicy', 'starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);
    $this->pool = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 10, 'name' => 'Parter', 'price' => 12000]);
});

function joinWaitlist(TicketType|int $pool, mixed $quantity = 1)
{
    $id = $pool instanceof TicketType ? $pool->id : $pool;

    return test()->postJson("/api/ticket-types/{$id}/waitlist", ['quantity' => $quantity]);
}

it('puts an attendee at the end of the queue of a sold out pool', function () {
    WaitlistEntry::factory()->for($this->pool)->count(2)->create();

    $this->actingAs($this->attendee);

    $response = joinWaitlist($this->pool, 2)->assertCreated();

    $entry = WaitlistEntry::query()->where('user_id', $this->attendee->id)->sole();

    $response->assertExactJson([
        'id' => $entry->id,
        'status' => 'WAITING',
        'quantity' => 2,
        'position' => 3,
        'offeredUntil' => null,
        'orderId' => null,
        'ticketType' => ['id' => $this->pool->id, 'name' => 'Parter', 'price' => 12000],
        'event' => ['id' => $this->event->id, 'title' => 'Jazz w Piwnicy', 'startsAt' => '2026-12-01T18:00:00Z'],
        'createdAt' => '2026-11-14T12:00:00Z',
    ]);
});

it('sends a buyer to the normal purchase while enough seats are free', function () {
    $this->pool->forceFill(['sold' => 8])->save();

    $this->actingAs($this->attendee);

    joinWaitlist($this->pool, 2)->assertConflict()->assertJsonPath('error.code', 'WAITLIST_NOT_NEEDED');
    joinWaitlist($this->pool, 3)->assertCreated();
});

it('counts seats held for orders and offers as taken', function () {
    $this->pool->forceFill(['sold' => 0])->save();

    OrderFixtures::holding($this->pool, 10);

    $this->actingAs($this->attendee);

    joinWaitlist($this->pool)->assertCreated();
});

it('keeps one active entry per user and pool', function () {
    $this->actingAs($this->attendee);

    joinWaitlist($this->pool)->assertCreated();
    joinWaitlist($this->pool)->assertConflict()->assertJsonPath('error.code', 'ALREADY_ON_WAITLIST');

    WaitlistEntry::query()->where('user_id', $this->attendee->id)->update(['status' => WaitlistStatus::Offered]);

    joinWaitlist($this->pool)->assertConflict()->assertJsonPath('error.code', 'ALREADY_ON_WAITLIST');

    WaitlistEntry::query()->where('user_id', $this->attendee->id)->update(['status' => WaitlistStatus::Expired]);

    joinWaitlist($this->pool)->assertCreated();
});

it('refuses the queue of an event that is not on sale', function (Closure $closeSales) {
    $closeSales($this->event);

    $this->actingAs($this->attendee);

    joinWaitlist($this->pool)->assertConflict()->assertJsonPath('error.code', 'SALES_CLOSED');
})->with([
    'cancelled' => [fn (EventModel $event) => $event->forceFill(['status' => EventStatus::Cancelled])->save()],
    'draft' => [fn (EventModel $event) => $event->forceFill(['status' => EventStatus::Draft])->save()],
    'already started' => [fn (EventModel $event) => test()->travelTo($event->starts_at)],
]);

it('counts bought tickets of the event against the limit of six', function () {
    TicketFixtures::paidOrder([4], $this->attendee, $this->event);

    $this->actingAs($this->attendee);

    joinWaitlist($this->pool, 3)->assertConflict()->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');
    joinWaitlist($this->pool, 2)->assertCreated();
});

it('validates the quantity', function (mixed $quantity) {
    $this->actingAs($this->attendee);

    joinWaitlist($this->pool, $quantity)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'quantity');
})->with([0, 7, 'two', null]);

it('checks joining in the order 401, 403 role, 400, 404, 409', function () {
    joinWaitlist(999999, 0)->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    joinWaitlist(999999, 0)->assertForbidden();

    $this->actingAs($this->attendee);
    joinWaitlist(999999, 0)->assertStatus(400);
    $this->postJson("/api/ticket-types/{$this->pool->id}/waitlist", ['quantity' => 1, 'userId' => 5])->assertStatus(400);
    joinWaitlist(999999, 1)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

    $this->pool->forceFill(['sold' => 0])->save();

    joinWaitlist($this->pool, 1)->assertConflict();
});

it('lets the user leave the queue', function () {
    $entry = WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->create();

    $this->actingAs($this->attendee)
        ->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")
        ->assertNoContent();

    expect($entry->fresh()->status)->toBe(WaitlistStatus::Cancelled);

    $this->postJson("/api/ticket-types/{$this->pool->id}/waitlist", ['quantity' => 1])->assertCreated();
});

it('passes the seats of a left offer to the next person in line', function () {
    Event::fake([WaitlistOfferCreated::class]);

    $this->pool->forceFill(['sold' => 8, 'held' => 2])->save();

    $offer = WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->offered()->create(['quantity' => 2]);
    $next = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 2]);

    $this->actingAs($this->attendee)
        ->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")
        ->assertNoContent();

    expect($offer->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($next->fresh()->status)->toBe(WaitlistStatus::Offered)
        ->and($this->pool->fresh()->held)->toBe(2);

    Event::assertDispatched(WaitlistOfferCreated::class, fn (WaitlistOfferCreated $event) => $event->waitlistEntryId === $next->id);
});

it('returns the seats of a left offer to the pool when nobody else waits', function () {
    $this->pool->forceFill(['sold' => 8, 'held' => 2])->save();

    WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->offered()->create(['quantity' => 2]);

    $this->actingAs($this->attendee)
        ->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")
        ->assertNoContent();

    expect($this->pool->fresh())->held->toBe(0)->version->toBe(1);
});

it('answers 404 when the user is not in the queue of the pool', function () {
    WaitlistEntry::factory()->for($this->pool)->create();
    WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->converted()->create();

    $this->actingAs($this->attendee)
        ->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")
        ->assertNotFound();

    $this->deleteJson('/api/ticket-types/999999/waitlist')->assertNotFound();
});

it('lets only attendees leave a queue', function () {
    $this->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create())
        ->deleteJson("/api/ticket-types/{$this->pool->id}/waitlist")
        ->assertForbidden();
});

it('lists own entries newest first with the queue position of waiting ones', function () {
    $otherPool = TicketType::factory()->for($this->event)->soldOut()->create();

    WaitlistEntry::factory()->for($this->pool)->create();
    $waiting = WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->create();

    $this->travel(1)->minute();

    $offered = WaitlistEntry::factory()->for($this->attendee)->for($otherPool)->offered()->create();
    WaitlistEntry::factory()->for($otherPool)->create();

    $this->actingAs($this->attendee)
        ->getJson('/api/waitlist')
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 2, 'totalPages' => 1])
        ->assertJsonPath('data.0.id', $offered->id)
        ->assertJsonPath('data.0.status', 'OFFERED')
        ->assertJsonPath('data.0.position', null)
        ->assertJsonPath('data.0.offeredUntil', '2026-11-14T12:31:00Z')
        ->assertJsonPath('data.1.id', $waiting->id)
        ->assertJsonPath('data.1.position', 2);
});

it('moves up in the queue when people ahead get an offer or leave', function () {
    $ahead = WaitlistEntry::factory()->for($this->pool)->count(2)->create();
    WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->create();

    $ahead->first()->update(['status' => WaitlistStatus::Offered]);
    $ahead->last()->update(['status' => WaitlistStatus::Cancelled]);

    $this->actingAs($this->attendee)
        ->getJson('/api/waitlist')
        ->assertJsonPath('data.0.position', 1);
});

it('filters own entries by status', function () {
    WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->expired()->create();
    $waiting = WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->create();

    $this->actingAs($this->attendee)
        ->getJson('/api/waitlist?status=WAITING&limit=100')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $waiting->id);

    $this->getJson('/api/waitlist?status=LOST')->assertStatus(400);
    $this->getJson('/api/waitlist?limit=101')->assertStatus(400);
});

it('lists entries with a constant number of queries', function () {
    $this->actingAs($this->attendee);

    $count = function () {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson('/api/waitlist')->assertOk();

        return count(DB::getQueryLog());
    };

    WaitlistEntry::factory()->for($this->attendee)->create();

    $one = $count();

    WaitlistEntry::factory()->for($this->attendee)->count(3)->create();

    expect($count())->toBe($one);
});

it('lets only attendees list entries', function () {
    $this->getJson('/api/waitlist')->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create())->getJson('/api/waitlist')->assertForbidden();
});
