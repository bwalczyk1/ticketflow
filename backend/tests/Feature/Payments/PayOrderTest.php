<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderPlaced;
use App\Models\Event as EventModel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->attendee = User::factory()->create();
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 2, $this->attendee, ['total' => 24000]);
    $this->path = "/api/orders/{$this->order->id}/pay";
    $this->actingAs($this->attendee);
});

it('starts the payment and dispatches OrderPlaced', function () {
    Event::fake([OrderPlaced::class]);

    $this->postJson($this->path)
        ->assertStatus(202)
        ->assertJsonPath('id', $this->order->id)
        ->assertJsonPath('status', 'PENDING')
        ->assertJsonPath('paymentStatus', 'PENDING');

    $payment = Payment::firstWhere('order_id', $this->order->id);

    expect($payment)
        ->status->toBe(PaymentStatus::Pending)
        ->amount->toBe(24000);

    Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->orderId === $this->order->id);
});

it('answers 409 to a second pay', function () {
    Event::fake([OrderPlaced::class]);

    $this->postJson($this->path)->assertStatus(202);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'PAYMENT_ALREADY_STARTED');

    expect(Payment::count())->toBe(1);

    Event::assertDispatchedTimes(OrderPlaced::class, 1);
});

it('answers 409 for a payment still in progress after the hold ended', function () {
    Payment::factory()->for($this->order)->create();

    $this->travelTo($this->order->expires_at->addMinute());

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'PAYMENT_ALREADY_STARTED');
});

it('accepts payment one second before the hold ends', function () {
    $this->travelTo($this->order->expires_at->subSecond());

    $this->postJson($this->path)->assertStatus(202);
});

it('answers 410 from the exact end of the hold, before the scheduler runs', function (int $secondsAfter) {
    Event::fake([OrderPlaced::class]);

    $this->travelTo($this->order->expires_at->addSeconds($secondsAfter));

    $this->postJson($this->path)
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'RESERVATION_EXPIRED');

    expect(Payment::count())->toBe(0);

    Event::assertNotDispatched(OrderPlaced::class);
})->with([
    'on the boundary' => 0,
    'just after' => 1,
]);

it('answers 410 for an expired order', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Expired]);

    $this->postJson($this->path)
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'RESERVATION_EXPIRED');
});

it('answers 409 for a cancelled order', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Cancelled]);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('answers 409 for a finished payment', function (OrderStatus $status, PaymentStatus $paymentStatus) {
    Order::query()->whereKey($this->order->id)->update(['status' => $status]);
    Payment::factory()->for($this->order)->create(['status' => $paymentStatus]);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'PAYMENT_ALREADY_STARTED');
})->with([
    'paid' => [OrderStatus::Paid, PaymentStatus::Succeeded],
    'failed' => [OrderStatus::Failed, PaymentStatus::Failed],
]);

it('answers 409 when the sale closed during the hold', function (array $eventChange) {
    EventModel::query()->whereKey($this->pool->event_id)->update($eventChange);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'SALES_CLOSED');

    expect(Payment::count())->toBe(0);
})->with([
    'event cancelled' => fn () => ['status' => 'CANCELLED'],
    'event started' => fn () => ['starts_at' => now()->subMinute()],
]);

it('answers 404 for an order of another user', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->path)->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('checks in the order 401, 403 role, 404, 410', function () {
    $this->order->update(['status' => OrderStatus::Expired]);

    auth()->forgetGuards();

    $this->postJson('/api/orders/999999/pay')->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->postJson('/api/orders/999999/pay')->assertForbidden();

    $this->actingAs($this->attendee);
    $this->postJson('/api/orders/999999/pay')->assertNotFound();
    $this->postJson($this->path)->assertStatus(410);
});
