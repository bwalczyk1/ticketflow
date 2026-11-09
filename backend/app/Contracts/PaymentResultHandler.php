<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * The single entry for payment results, used by both the job and a webhook. Both methods are idempotent.
 */
interface PaymentResultHandler
{
    public function succeeded(Payment $payment, ?string $providerRef = null): void;

    public function failed(Payment $payment, string $reason): void;
}
