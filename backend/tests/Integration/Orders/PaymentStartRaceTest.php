<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\PaymentAlreadyStarted;
use App\Exceptions\Domain\ReservationExpired;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Orders\CancelOrder;
use App\Services\Payments\StartPayment;
use Illuminate\Database\QueryException;
use Tests\Feature\Orders\OrderFixtures;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 2);
    $this->other = secondConnection();
});

it('refuses a payment when the expiry committed just before the lock', function () {
    Interleave::before(Interleave::ORDER_LOCK, function () {
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Expired->value]);
    });

    expect(fn () => app(StartPayment::class)->handle($this->order))->toThrow(ReservationExpired::class);

    expect(Payment::count())->toBe(0);
});

it('answers PAYMENT_ALREADY_STARTED to the loser of two parallel pays', function () {
    Interleave::before(Interleave::ORDER_LOCK, fn () => Interleave::commitPayment($this->other, $this->order));

    expect(fn () => app(StartPayment::class)->handle($this->order))->toThrow(PaymentAlreadyStarted::class);

    expect(Payment::count())->toBe(1);
});

it('keeps the order locked until the payment is committed', function () {
    $this->other->statement('SET SESSION innodb_lock_wait_timeout = 1');

    $blocked = false;

    Interleave::before('insert into `payments`', function () use (&$blocked) {
        try {
            $this->other->transaction(function () {
                $this->other->table('orders')->where('id', $this->order->id)->lockForUpdate()->first();
            });
        } catch (QueryException) {
            $blocked = true;
        }
    });

    app(StartPayment::class)->handle($this->order);

    expect($blocked)->toBeTrue();
});

it('refuses to cancel when a payment committed just before the lock', function () {
    Interleave::before(Interleave::ORDER_LOCK, fn () => Interleave::commitPayment($this->other, $this->order));

    expect(fn () => app(CancelOrder::class)->handle($this->order))->toThrow(PaymentAlreadyStarted::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($this->pool->fresh()->held)->toBe(2);
});
