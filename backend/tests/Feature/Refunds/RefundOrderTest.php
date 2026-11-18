<?php

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Events\Refunds\OrderRefunded;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventBus;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    EventBus::fake([OrderRefunded::class]);

    $this->travelTo('2026-11-14 12:00:00');

    $this->attendee = User::factory()->create();
    $this->event = Event::factory()->create(['starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);
    $this->order = TicketFixtures::paidOrder([2], $this->attendee, $this->event);
    $this->path = "/api/orders/{$this->order->id}/refund";

    $this->actingAs($this->attendee);
});

it('refunds an own paid order and dispatches OrderRefunded', function () {
    $this->postJson($this->path, ['reason' => 'Nie mogę przyjść'])
        ->assertOk()
        ->assertJsonPath('id', $this->order->id)
        ->assertJsonPath('status', 'REFUNDED')
        ->assertJsonPath('refundableUntil', null)
        ->assertJsonPath('refund.status', 'PENDING')
        ->assertJsonPath('refund.amount', $this->order->total)
        ->assertJsonPath('refund.reason', 'Nie mogę przyjść')
        ->assertJsonPath('refund.createdAt', '2026-11-14T12:00:00Z');

    $refund = Refund::query()->sole();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($refund->order_id)->toBe($this->order->id)
        ->and($refund->amount)->toBe($this->order->total)
        ->and($refund->status)->toBe(RefundStatus::Pending);

    EventBus::assertDispatched(
        OrderRefunded::class,
        fn (OrderRefunded $event) => $event->orderId === $this->order->id && $event->refundId === $refund->id,
    );
});

it('refunds without a reason', function (array $body) {
    $this->postJson($this->path, $body)
        ->assertOk()
        ->assertJsonPath('refund.reason', null);
})->with([
    'empty body' => [[]],
    'null reason' => [['reason' => null]],
]);

it('refunds exactly 48 hours before the start', function () {
    $this->travelTo('2026-11-29 18:00:00');

    $this->postJson($this->path)->assertOk();
});

it('answers 409 REFUND_WINDOW_CLOSED after the deadline', function () {
    $this->travelTo('2026-11-29 18:00:01');

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'REFUND_WINDOW_CLOSED');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(Refund::query()->count())->toBe(0);

    EventBus::assertNotDispatched(OrderRefunded::class);
});

it('answers 409 ORDER_NOT_REFUNDABLE for an order that is not paid', function (OrderStatus $status) {
    Order::query()->whereKey($this->order->id)->update(['status' => $status]);

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'ORDER_NOT_REFUNDABLE');

    expect(Refund::query()->count())->toBe(0);
})->with([OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled]);

it('answers 409 to a second refund and keeps one refund', function () {
    $this->postJson($this->path)->assertOk();

    $this->postJson($this->path)
        ->assertConflict()
        ->assertJsonPath('error.code', 'ORDER_NOT_REFUNDABLE');

    expect(Refund::query()->count())->toBe(1);

    EventBus::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('checks the status before the deadline', function () {
    $this->travelTo('2026-12-02 12:00:00');

    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Expired]);

    $this->postJson($this->path)->assertJsonPath('error.code', 'ORDER_NOT_REFUNDABLE');
});

it('answers 404 for an order of another user', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson($this->path)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('answers 400 for an invalid reason', function (array $body) {
    $this->postJson($this->path, $body)
        ->assertBadRequest()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
})->with([
    'too long' => [['reason' => str_repeat('a', 501)]],
    'not a string' => [['reason' => 12]],
    'unknown field' => [['amount' => 100]],
]);

it('checks in the order 401, 403 role, 400, 404, 404 ownership, 409', function () {
    $foreign = TicketFixtures::paidOrder([1], event: $this->event);
    $tooLong = ['reason' => str_repeat('a', 501)];

    auth()->forgetGuards();
    $this->postJson('/api/orders/999999/refund', $tooLong)->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->postJson('/api/orders/999999/refund', $tooLong)->assertForbidden();

    $this->actingAs($this->attendee);
    $this->postJson('/api/orders/999999/refund', $tooLong)->assertBadRequest();
    $this->postJson('/api/orders/999999/refund')->assertNotFound();
    $this->postJson("/api/orders/{$foreign->id}/refund")->assertNotFound();

    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Pending]);

    $this->postJson($this->path)->assertConflict();
});
