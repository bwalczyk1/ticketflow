<?php

namespace App\Contracts;

use App\Exceptions\Domain\PaymentDeclined;
use App\Models\Payment;
use App\Models\Refund;

interface PaymentGateway
{
    /**
     * Returns the reference of the settled payment at the provider. Safe to call twice: the
     * idempotency key is payment:{id}.
     *
     * @throws PaymentDeclined
     */
    public function startPayment(Payment $payment): string;

    /**
     * Safe to call twice: the idempotency key is refund:{id}.
     */
    public function refund(Refund $refund): void;
}
