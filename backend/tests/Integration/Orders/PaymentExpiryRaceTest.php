<?php

use App\Contracts\PaymentResultHandler;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\ReservationExpired;
use App\Events\Payments\PaymentFailed;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Orders\ExpireReservations;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\OrderFixtures;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 2);
    $this->other = secondConnection();

    Event::fake([OrderPaid::class, PaymentFailed::class, ReservationExpired::class]);
});

it('does not expire an order whose payment committed after the scheduler listed it', function () {
    $this->travelTo($this->order->expires_at);

    Interleave::before(Interleave::ORDER_ROW, fn () => Interleave::commitPayment($this->other, $this->order));

    expect(app(ExpireReservations::class)->handle())->toBe(0)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Pending);

    Event::assertNotDispatched(ReservationExpired::class);
});

it('does not expire an order paid after the scheduler listed it', function () {
    $this->travelTo($this->order->expires_at);

    Interleave::before(Interleave::ORDER_ROW, function () {
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Paid->value]);
    });

    expect(app(ExpireReservations::class)->handle())->toBe(0)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);

    Event::assertNotDispatched(ReservationExpired::class);
});

it('does not pay an order expired just before the payment result arrived', function () {
    $payment = Payment::factory()->for($this->order)->create();

    Interleave::before(Interleave::ORDER_LOCK, function () {
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Expired->value]);
    });

    app(PaymentResultHandler::class)->succeeded($payment, 'ref_1');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->pool->fresh())->sold->toBe(0)->held->toBe(2);

    Event::assertNotDispatched(OrderPaid::class);
});

it('does not fail an order expired just before the payment result arrived', function () {
    $payment = Payment::factory()->for($this->order)->create();

    Interleave::before(Interleave::ORDER_LOCK, function () {
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Expired->value]);
    });

    app(PaymentResultHandler::class)->failed($payment, 'Card declined');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Event::assertNotDispatched(PaymentFailed::class);
});

it('settles a repeated result only once when it was committed just before the lock', function () {
    $payment = Payment::factory()->for($this->order)->create();

    Interleave::before(Interleave::ORDER_LOCK, function () use ($payment) {
        $this->other->table('payments')->where('id', $payment->id)->update(['status' => PaymentStatus::Succeeded->value]);
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Paid->value]);
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['held' => 0, 'sold' => 2]);
    });

    app(PaymentResultHandler::class)->succeeded($payment);

    expect($this->pool->fresh())->sold->toBe(2)->held->toBe(0);

    Event::assertNotDispatched(OrderPaid::class);
});
