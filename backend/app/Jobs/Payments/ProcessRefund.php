<?php

namespace App\Jobs\Payments;

use App\Enums\QueueName;
use App\Services\Payments\SettleRefund;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function failed(Throwable $exception): void
    {
        Log::error('Processing a refund failed', ['refund_id' => $this->refundId, 'error' => $exception->getMessage()]);
    }
}
