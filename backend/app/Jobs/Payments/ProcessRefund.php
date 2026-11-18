<?php

namespace App\Jobs\Payments;

use App\Enums\QueueName;
use App\Services\Payments\SettleRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a refund that no OrderRefunded announces, such as a late payment, to the gateway.
 */
class ProcessRefund implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $refundId)
    {
        $this->onQueue(QueueName::Payments->value);
        $this->afterCommit();
    }

    public function handle(SettleRefund $settleRefund): void
    {
        $settleRefund->handle($this->refundId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Processing a refund failed', ['refund_id' => $this->refundId, 'error' => $exception->getMessage()]);
    }
}
