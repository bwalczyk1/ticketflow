<?php

namespace App\Listeners\Orders;

use App\Enums\QueueName;
use App\Events\Orders\ReservationExpired;
use App\Events\Payments\PaymentFailed;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue(QueueName::Orders)]
class ReleaseSeats implements ShouldQueue
{
    public function __construct(private ReleaseOrderSeats $releaseOrderSeats) {}

    public function handle(PaymentFailed|ReservationExpired $event): void
    {
        $this->releaseOrderSeats->handle($event->orderId);
    }

    public function failed(PaymentFailed|ReservationExpired $event, Throwable $exception): void
    {
        Log::error('Releasing seats failed', ['order_id' => $event->orderId, 'error' => $exception->getMessage()]);
    }
}
