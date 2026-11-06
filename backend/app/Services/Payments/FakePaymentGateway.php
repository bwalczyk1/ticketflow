<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentStart;
use App\Exceptions\Domain\PaymentDeclined;
use App\Models\Payment;
use App\Models\Refund;
use Closure;
use Illuminate\Support\Sleep;

/**
 * Simulated gateway. It settles inside startPayment: returning means paid, PaymentDeclined means
 * refused. The answer depends only on the idempotency key, so asking twice about one payment
 * gives the same answer, like a real gateway that remembers the key.
 */
class FakePaymentGateway implements PaymentGateway
{
    /**
     * @param  (Closure(string): float)|null  $random  maps an idempotency key to a number from 0 up to 1
     */
    public function __construct(
        private float $failureRate,
        private ?Closure $random = null,
        private int $minDelayMs = 0,
        private int $maxDelayMs = 0,
    ) {}

    /**
     * @throws PaymentDeclined
     */
    public function startPayment(Payment $payment): PaymentStart
    {
        $key = "payment:{$payment->id}";

        if ($this->maxDelayMs > 0) {
            Sleep::for(random_int($this->minDelayMs, $this->maxDelayMs))->milliseconds();
        }

        if ($this->draw($key) < $this->failureRate) {
            throw new PaymentDeclined;
        }

        return new PaymentStart(providerRef: 'fake_'.substr(hash('sha256', $key), 0, 16));
    }

    /**
     * No money moves in the simulation, so repeating refund:{id} changes nothing.
     */
    public function refund(Refund $refund): void {}

    private function draw(string $key): float
    {
        if ($this->random !== null) {
            return ($this->random)($key);
        }

        return hexdec(hash('xxh32', $key)) / 2 ** 32;
    }
}
