<?php

use App\Contracts\PaymentResultHandler;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Payments\PaymentFailed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Payments\RefundLatePayment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 3);
    $this->payment = Payment::factory()->for($this->order)->create();
    $this->results = app(PaymentResultHandler::class);
});

function endOrder(Order $order, OrderStatus $status): void
{
    Order::query()->whereKey($order->id)->update(['status' => $status, 'seats_released_at' => now()]);
}

it('pays the order and moves the seats from held to sold', function () {
    Event::fake([OrderPaid::class]);

    $this->results->succeeded($this->payment, 'ref_1');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->payment->fresh())
        ->status->toBe(PaymentStatus::Succeeded)
        ->provider_ref->toBe('ref_1')
        ->and($this->pool->fresh())
        ->held->toBe(0)
        ->sold->toBe(3)
        ->version->toBe(1);

    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->orderId === $this->order->id);
});

it('moves the seats of every pool of the order', function () {
    $second = TicketType::factory()->create(['event_id' => $this->pool->event_id]);

    OrderItem::factory()->for($this->order)->create(['ticket_type_id' => $second->id, 'quantity' => 1]);
    TicketType::query()->whereKey($second->id)->increment('held', 1);

    $this->results->succeeded($this->payment);

    expect($this->pool->fresh()->sold)->toBe(3)
        ->and($second->fresh())->sold->toBe(1)->held->toBe(0);
});

it('counts a repeated success once', function () {
    Event::fake([OrderPaid::class]);

    $this->results->succeeded($this->payment, 'ref_1');
    $this->results->succeeded($this->payment->fresh(), 'ref_2');

    expect($this->pool->fresh())->held->toBe(0)->sold->toBe(3)
        ->and($this->payment->fresh()->provider_ref)->toBe('ref_1');

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('pays a pending order whose hold ended while the payment was running', function () {
    $this->travelTo($this->order->expires_at->addMinute());

    $this->results->succeeded($this->payment);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('does not pay an order in a final state and starts the refund path', function (OrderStatus $status) {
    Event::fake([OrderPaid::class]);
    endOrder($this->order, $status);

    TicketType::query()->whereKey($this->pool->id)->update(['held' => 0]);

    $refund = $this->mock(RefundLatePayment::class);

    $refund->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Payment $payment, Order $order) => $payment->is($this->payment) && $order->is($this->order));

    app(PaymentResultHandler::class)->succeeded($this->payment, 'ref_1');

    expect($this->order->fresh()->status)->toBe($status)
        ->and($this->payment->fresh())
        ->status->toBe(PaymentStatus::Succeeded)
        ->provider_ref->toBe('ref_1')
        ->and($this->pool->fresh())
        ->held->toBe(0)
        ->sold->toBe(0);

    Event::assertNotDispatched(OrderPaid::class);
})->with([OrderStatus::Expired, OrderStatus::Cancelled]);

it('starts the refund path of a late success only once', function () {
    endOrder($this->order, OrderStatus::Expired);

    $this->mock(RefundLatePayment::class)->shouldReceive('handle')->once();

    $results = app(PaymentResultHandler::class);

    $results->succeeded($this->payment);
    $results->succeeded($this->payment->fresh());
});

it('logs a late success with the order and payment ids', function () {
    Log::spy();

    endOrder($this->order, OrderStatus::Expired);

    $this->results->succeeded($this->payment);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context === ['order_id' => $this->order->id, 'payment_id' => $this->payment->id]);
});

it('fails the order and reports the reason', function () {
    Event::fake([PaymentFailed::class]);

    $this->results->failed($this->payment, 'Card declined');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Event::assertDispatched(
        PaymentFailed::class,
        fn (PaymentFailed $event) => $event->orderId === $this->order->id && $event->reason === 'Card declined',
    );
});

it('counts a repeated failure once', function () {
    Event::fake([PaymentFailed::class]);

    $this->results->failed($this->payment, 'Card declined');
    $this->results->failed($this->payment->fresh(), 'Card declined');

    Event::assertDispatchedTimes(PaymentFailed::class, 1);
});

it('only records a failure of an order that already ended', function () {
    Event::fake([PaymentFailed::class]);
    endOrder($this->order, OrderStatus::Expired);

    $this->results->failed($this->payment, 'Card declined');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Event::assertNotDispatched(PaymentFailed::class);
});

it('ignores a failure after a success and a success after a failure', function () {
    Event::fake([OrderPaid::class, PaymentFailed::class]);

    $paid = Payment::factory()->succeeded()->for(OrderFixtures::holding($this->pool, 1, attributes: ['status' => OrderStatus::Paid]))->create();
    $failed = Payment::factory()->failed()->for(OrderFixtures::holding($this->pool, 1, attributes: ['status' => OrderStatus::Failed]))->create();

    $this->results->failed($paid, 'Too late');
    $this->results->succeeded($failed);

    expect($paid->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($failed->fresh()->status)->toBe(PaymentStatus::Failed);

    Event::assertNothingDispatched();
});
