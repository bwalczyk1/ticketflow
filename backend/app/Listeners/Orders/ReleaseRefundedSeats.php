<?php

namespace App\Listeners\Orders;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;

#[Queue(QueueName::Orders)]
class ReleaseRefundedSeats implements ShouldQueue
{
    public function __construct(private ReleaseOrderSeats $releaseOrderSeats) {}

    public function handle(OrderRefunded $event): void
    {
        $this->releaseOrderSeats->handleRefunded($event->orderId);
    }
}
