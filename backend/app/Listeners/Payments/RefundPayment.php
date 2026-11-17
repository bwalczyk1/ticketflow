<?php

namespace App\Listeners\Payments;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Payments\SettleRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;

#[Queue(QueueName::Payments)]
class RefundPayment implements ShouldQueue
{
    public function __construct(private SettleRefund $settleRefund) {}

    /**
     * After the last attempt the refund stays FAILED and the job stays in Horizon, where it can be
     * retried by hand.
     */
    public function handle(OrderRefunded $event): void
    {
        $this->settleRefund->handle($event->refundId);
    }
}
