<?php

namespace App\Listeners\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentResultHandler;
use App\Enums\PaymentStatus;
use App\Enums\QueueName;
use App\Events\Orders\OrderPlaced;
use App\Exceptions\Domain\PaymentDeclined;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPayment implements ShouldQueue
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentResultHandler $results,
    ) {}

    public function viaQueue(): string
    {
        return QueueName::Payments->value;
    }

    /**
     * A second job of the same order is dropped, because the first one is doing its work and a
     * crashed first job is retried by the queue itself. The lock outlives the job timeout.
     *
     * @return list<object>
     */
    public function middleware(OrderPlaced $event): array
    {
        return [(new WithoutOverlapping("order:{$event->orderId}"))->dontRelease()->expireAfter(90)];
    }

    /**
     * A gateway that returns a redirect reports its result later through a webhook. Any other
     * error from the gateway leaves the payment pending, so the queue retries with the same key.
     */
    public function handle(OrderPlaced $event): void
    {
        $payment = Payment::query()->firstWhere('order_id', $event->orderId);

        if ($payment?->status !== PaymentStatus::Pending) {
            return;
        }

        try {
            $start = $this->gateway->startPayment($payment);
        } catch (PaymentDeclined $e) {
            $this->results->failed($payment, $e->getMessage());

            return;
        }

        if ($start->redirectUrl === null) {
            $this->results->succeeded($payment, $start->providerRef);
        }
    }

    /**
     * The payment stays pending and ExpireReservations picks the order up after the grace period.
     */
    public function failed(OrderPlaced $event, Throwable $exception): void
    {
        Log::error('Processing a payment failed', ['order_id' => $event->orderId, 'error' => $exception->getMessage()]);
    }
}
