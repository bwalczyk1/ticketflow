<?php

namespace App\Listeners\Orders;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleaseRefundedSeats implements ShouldQueue
{
    public function __construct(private ReleaseOrderSeats $releaseOrderSeats) {}

    public function viaQueue(): string
    {
        return QueueName::Orders->value;
    }

    public function handle(OrderRefunded $event): void
    {
        $this->releaseOrderSeats->handleRefunded($event->orderId);
    }

    public function failed(OrderRefunded $event, Throwable $exception): void
    {
        Log::error('Releasing refunded seats failed', ['order_id' => $event->orderId, 'error' => $exception->getMessage()]);
    }
}
