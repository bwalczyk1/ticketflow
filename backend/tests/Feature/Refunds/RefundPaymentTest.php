<?php

use App\Contracts\PaymentGateway;
use App\Enums\RefundStatus;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Payments\RefundPayment;
use App\Models\Order;
use App\Models\Refund;
use Mockery\MockInterface;

beforeEach(function () {
    $this->order = Order::factory()->refunded()->create();
    $this->refund = Refund::factory()->for($this->order)->create(['amount' => $this->order->total]);
    $this->event = new OrderRefunded($this->order->id, $this->refund->id);
});

it('returns the money through the gateway and marks the refund succeeded', function () {
    $this->mock(PaymentGateway::class, function (MockInterface $gateway) {
        $gateway->shouldReceive('refund')->once()->withArgs(fn (Refund $refund) => $refund->id === $this->refund->id);
    });

    app(RefundPayment::class)->handle($this->event);

    expect($this->refund->fresh()->status)->toBe(RefundStatus::Succeeded);
});

it('does not ask the gateway again for a refund that succeeded', function () {
    $this->mock(PaymentGateway::class, function (MockInterface $gateway) {
        $gateway->shouldReceive('refund')->once();
    });

    app(RefundPayment::class)->handle($this->event);
    app(RefundPayment::class)->handle($this->event);

    expect($this->refund->fresh()->status)->toBe(RefundStatus::Succeeded);
});

it('marks the refund failed and throws, so the queue retries it', function () {
    $this->mock(PaymentGateway::class, function (MockInterface $gateway) {
        $gateway->shouldReceive('refund')->once()->andThrow(new RuntimeException('Gateway unavailable'));
    });

    expect(fn () => app(RefundPayment::class)->handle($this->event))->toThrow(RuntimeException::class);

    expect($this->refund->fresh()->status)->toBe(RefundStatus::Failed);
});

it('finishes a failed refund on the retry', function () {
    $this->refund->update(['status' => RefundStatus::Failed]);

    $this->mock(PaymentGateway::class, function (MockInterface $gateway) {
        $gateway->shouldReceive('refund')->once();
    });

    app(RefundPayment::class)->handle($this->event);

    expect($this->refund->fresh()->status)->toBe(RefundStatus::Succeeded);
});

it('does nothing for a missing refund', function () {
    $this->mock(PaymentGateway::class, function (MockInterface $gateway) {
        $gateway->shouldNotReceive('refund');
    });

    app(RefundPayment::class)->handle(new OrderRefunded($this->order->id, 999999));

    expect($this->refund->fresh()->status)->toBe(RefundStatus::Pending);
});
