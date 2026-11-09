<?php

namespace App\Contracts;

use App\Data\PaymentStart;
use App\Models\Payment;
use App\Models\Refund;

interface PaymentGateway
{
    /**
     * Safe to call twice: the idempotency key is payment:{id}.
     */
    public function startPayment(Payment $payment): PaymentStart;

    /**
     * Safe to call twice: the idempotency key is refund:{id}.
     */
    public function refund(Refund $refund): void;
}
