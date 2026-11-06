<?php

namespace App\Listeners\Orders;

use App\Enums\QueueName;
use App\Events\Orders\ReservationExpired;
use App\Events\Payments\PaymentFailed;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleaseSeats implements ShouldQueue
{
    public function __construct(private ReleaseOrderSeats $releaseOrderSeats) {}

    public function viaQueue(): string
    {
        return QueueName::Orders->value;
    }

    public function handle(PaymentFailed|ReservationExpired $event): void
    {
        $this->releaseOrderSeats->handle($event->orderId);
    }

    public function failed(PaymentFailed|ReservationExpired $event, Throwable $exception): void
    {
        Log::error('Releasing seats failed', ['order_id' => $event->orderId, 'error' => $exception->getMessage()]);
    }
}
