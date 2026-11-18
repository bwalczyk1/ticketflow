<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Money arrived for an order that already ended, so it has to go back. The refunds module will
 * create the Refund here; until then the log entry is the record of what needs refunding.
 */
class RefundLatePayment
{
    public function handle(Payment $payment, Order $order): void
    {
        Log::warning('Payment succeeded for an order in a final state, a refund is needed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);
    }
}
