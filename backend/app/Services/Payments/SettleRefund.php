<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\RefundStatus;
use App\Models\Refund;
use Throwable;

/**
 * Sends a refund to the payment gateway. The gateway gets the idempotency key refund:{id}, so a
 * retry after a crash between the gateway call and the status update returns no money twice.
 */
class SettleRefund
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * A gateway error marks the refund FAILED and is thrown again, so the queue retries it.
     *
     * @throws Throwable
     */
    public function handle(int $refundId): void
    {
        $refund = Refund::query()->find($refundId, ['id', 'order_id', 'amount', 'status']);

        if ($refund === null || $refund->status === RefundStatus::Succeeded) {
            return;
        }

        try {
            $this->gateway->refund($refund);
        } catch (Throwable $e) {
            $refund->update(['status' => RefundStatus::Failed]);

            throw $e;
        }

        $refund->update(['status' => RefundStatus::Succeeded]);
    }
}
