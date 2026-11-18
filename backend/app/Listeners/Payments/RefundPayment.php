<?php

namespace App\Listeners\Payments;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Payments\SettleRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundPayment implements ShouldQueue
{
    public function __construct(private SettleRefund $settleRefund) {}

    public function viaQueue(): string
    {
        return QueueName::Payments->value;
    }

    public function handle(OrderRefunded $event): void
    {
        $this->settleRefund->handle($event->refundId);
    }

    /**
     * The refund stays FAILED and the job stays in Horizon, where it can be retried by hand.
     */
    public function failed(OrderRefunded $event, Throwable $exception): void
    {
        Log::error('Refunding a payment failed', ['order_id' => $event->orderId, 'refund_id' => $event->refundId, 'error' => $exception->getMessage()]);
    }
}
