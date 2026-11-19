<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            $gateway = config('ticketflow.payment_gateway');

            return match ($gateway) {
                'fake' => $this->fakeGateway(),
                default => throw new RuntimeException("Payment gateway {$gateway} is not implemented"),
            };
        });
    }

    private function fakeGateway(): FakePaymentGateway
    {
        $delayed = ! $this->app->runningUnitTests();

        return new FakePaymentGateway(
            failureRate: (float) config('ticketflow.fake_failure_rate'),
            minDelayMs: $delayed ? 1000 : 0,
            maxDelayMs: $delayed ? 3000 : 0,
        );
    }
}
