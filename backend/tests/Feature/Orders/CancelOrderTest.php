<?php

use App\Enums\OrderStatus;
use App\Events\Orders\SeatsReleased;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->attendee = User::factory()->create();
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 3, $this->attendee);
    $this->path = "/api/orders/{$this->order->id}/cancel";
    $this->actingAs($this->attendee);
});

it('cancels an own pending order and gives the seats back at once', function () {
    Event::fake([SeatsReleased::class]);

    $this->postJson($this->path)
        ->assertOk()
        ->assertJsonPath('id', $this->order->id)
        ->assertJsonPath('status', 'CANCELLED');

    expect($this->order->fresh())
        ->status->toBe(OrderStatus::Cancelled)
        ->seats_released_at->not->toBeNull()
        ->and($this->pool->fresh()->held)->toBe(0);

    Event::assertDispatched(
        SeatsReleased::class,
        fn (SeatsReleased $event) => $event->ticketTypeId === $this->pool->id && $event->quantity === 3,
    );
});

it('answers 409 to a second cancel and releases once', function () {
    $this->postJson($this->path)->assertOk();

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');

    expect($this->pool->fresh()->held)->toBe(0);
});

it('answers 409 for an order past the pending stage', function (OrderStatus $status) {
    Order::query()->whereKey($this->order->id)->update(['status' => $status]);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');

    expect($this->pool->fresh()->held)->toBe(3);
})->with([OrderStatus::Paid, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Refunded]);

it('answers 409 once the payment has started', function () {
    Payment::factory()->for($this->order)->create();

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'PAYMENT_ALREADY_STARTED');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($this->pool->fresh()->held)->toBe(3);
});

it('answers 404 for an order of another user', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->path)->assertNotFound();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('checks in the order 401, 403 role, 404', function () {
    auth()->forgetGuards();

    $this->postJson('/api/orders/999999/cancel')->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->postJson('/api/orders/999999/cancel')->assertForbidden();

    $this->actingAs($this->attendee);
    $this->postJson('/api/orders/999999/cancel')->assertNotFound();
});
