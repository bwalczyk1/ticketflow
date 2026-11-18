<?php

namespace App\Jobs\Payments;

use App\Enums\QueueName;
use App\Services\Payments\SettleRefund;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

/**
 * Sends a refund that no OrderRefunded announces, such as a late payment, to the gateway.
 */
#[Queue(QueueName::Payments)]
class ProcessRefund implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly int $refundId) {}

    public function handle(SettleRefund $settleRefund): void
    {
        $settleRefund->handle($this->refundId);
    }
}
