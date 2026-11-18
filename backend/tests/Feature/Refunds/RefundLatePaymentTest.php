<?php

use App\Contracts\PaymentResultHandler;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Enums\RefundStatus;
use App\Jobs\Payments\ProcessRefund;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\RefundLatePayment;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->order = Order::factory()->expired()->create(['total' => 12000]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => 12000]);
});

it('creates a refund of the late payment and sends it to the gateway in the background', function () {
    Queue::fake();

    app(PaymentResultHandler::class)->succeeded($this->payment, 'ref_1');

    $refund = Refund::query()->sole();

    expect($refund)
        ->order_id->toBe($this->order->id)
        ->amount->toBe(12000)
        ->reason->toBe(RefundLatePayment::REASON)
        ->status->toBe(RefundStatus::Pending)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Expired);

    Queue::assertPushedOn(QueueName::Payments->value, ProcessRefund::class, fn (ProcessRefund $job) => $job->refundId === $refund->id);
});

it('returns the late payment through the gateway', function () {
    app(PaymentResultHandler::class)->succeeded($this->payment, 'ref_1');

    expect(Refund::query()->sole()->status)->toBe(RefundStatus::Succeeded);
});

it('creates one refund when the late result arrives twice', function () {
    Queue::fake();

    $results = app(PaymentResultHandler::class);

    $results->succeeded($this->payment);
    $results->succeeded($this->payment->fresh());

    expect(Refund::query()->count())->toBe(1);

    Queue::assertPushed(ProcessRefund::class, 1);
});

it('shows the refund of a late payment on the order', function () {
    app(PaymentResultHandler::class)->succeeded($this->payment);

    $this->actingAs($this->order->user)
        ->getJson("/api/orders/{$this->order->id}")
        ->assertJsonPath('status', 'EXPIRED')
        ->assertJsonPath('refund.status', 'SUCCEEDED')
        ->assertJsonPath('refund.amount', 12000);
});
