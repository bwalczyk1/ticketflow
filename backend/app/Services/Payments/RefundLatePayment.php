<?php

namespace App\Services\Payments;

use App\Jobs\Payments\ProcessRefund;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Money arrived for an order that already ended, so it goes back through the same gateway refund
 * as a refunded order. The order keeps its final status; nothing else of a refund applies to it,
 * because it never had tickets, an invoice or seats.
 */
class RefundLatePayment
{
    public const REASON = 'Płatność dotarła po zamknięciu zamówienia';

    /**
     * Runs inside the transaction of SettlePayment, whose payment compare-and-set lets it run once
     * per payment; UNIQUE (order_id) on refunds stays as the last defence.
     */
    public function handle(Payment $payment, Order $order): void
    {
        Log::warning('Payment succeeded for an order in a final state, a refund is needed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);

        $refund = $order->refund()->create([
            'amount' => Payment::query()->whereKey($payment->id)->value('amount'),
            'reason' => self::REASON,
        ]);

        ProcessRefund::dispatch($refund->id);
    }
}
