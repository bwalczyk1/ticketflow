<?php

namespace App\Jobs\Refunds;

use App\Enums\QueueName;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Services\Refunds\StartRefund;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One order of a cancelled event, run inside the batch of RefundAllOrders, so Horizon shows the progress.
 */
class RefundOrder implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue(QueueName::Refunds->value);
    }

    /**
     * An order that is no longer paid, for example refunded by the attendee or by an earlier
     * run of this job, is skipped.
     */
    public function handle(StartRefund $startRefund): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $startRefund->forCancelledEvent($this->orderId);
        } catch (OrderNotRefundable) {
            return;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Refunding an order of a cancelled event failed', ['order_id' => $this->orderId, 'error' => $exception::class]);
    }
}
