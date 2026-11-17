<?php

namespace App\Jobs\Refunds;

use App\Enums\QueueName;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Services\Refunds\StartRefund;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

/**
 * One order of a cancelled event, run inside the batch of RefundAllOrders, so Horizon shows the progress.
 */
#[Queue(QueueName::Refunds)]
class RefundOrder implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public readonly int $orderId) {}

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
}
