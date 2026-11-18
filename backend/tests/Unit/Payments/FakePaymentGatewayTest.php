<?php

use App\Exceptions\Domain\PaymentDeclined;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Sleep;

afterEach(function () {
    Sleep::fake(false);
});

function fakePayment(int $id): Payment
{
    return (new Payment)->forceFill(['id' => $id]);
}

it('accepts a payment rolled at or above the failure rate', function (float $roll) {
    $gateway = new FakePaymentGateway(0.1, fn () => $roll);

    expect($gateway->startPayment(fakePayment(1))->providerRef)->toStartWith('fake_');
})->with([0.1, 0.5, 0.99]);

it('declines a payment rolled below the failure rate', function (float $roll) {
    (new FakePaymentGateway(0.1, fn () => $roll))->startPayment(fakePayment(1));
})->with([0.0, 0.09])->throws(PaymentDeclined::class);

it('never asks for a redirect', function () {
    $gateway = new FakePaymentGateway(0.0);

    expect($gateway->startPayment(fakePayment(1))->redirectUrl)->toBeNull();
});

it('draws the number for the idempotency key of the payment', function () {
    $keys = [];

    $gateway = new FakePaymentGateway(0.0, function (string $key) use (&$keys) {
        $keys[] = $key;

        return 0.5;
    });

    $gateway->startPayment(fakePayment(42));

    expect($keys)->toBe(['payment:42']);
});

it('never declines at rate 0 and always declines at rate 1', function () {
    $accepted = fn (float $rate) => collect(range(1, 500))->filter(function (int $id) use ($rate) {
        try {
            (new FakePaymentGateway($rate))->startPayment(fakePayment($id));

            return true;
        } catch (PaymentDeclined) {
            return false;
        }
    })->count();

    expect($accepted(0.0))->toBe(500)
        ->and($accepted(1.0))->toBe(0);
});

it('declines about the configured share of payments', function () {
    $gateway = new FakePaymentGateway(0.1);

    $declined = collect(range(1, 10000))->reject(function (int $id) use ($gateway) {
        try {
            $gateway->startPayment(fakePayment($id));

            return true;
        } catch (PaymentDeclined) {
            return false;
        }
    })->count();

    expect($declined)->toBeGreaterThan(900)->toBeLessThan(1100);
});

it('gives the same answer when the same payment is started twice', function () {
    $gateway = new FakePaymentGateway(0.5);

    $outcome = function (int $id) use ($gateway) {
        try {
            return $gateway->startPayment(fakePayment($id))->providerRef;
        } catch (PaymentDeclined) {
            return 'declined';
        }
    };

    foreach (range(1, 50) as $id) {
        expect($outcome($id))->toBe($outcome($id));
    }
});

it('waits within the given delay', function () {
    Sleep::fake();

    (new FakePaymentGateway(0.0, minDelayMs: 1000, maxDelayMs: 3000))->startPayment(fakePayment(1));

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 1000 && $duration->totalMilliseconds <= 3000);
});

it('does not wait without a delay', function () {
    Sleep::fake();

    (new FakePaymentGateway(0.0))->startPayment(fakePayment(1));

    Sleep::assertNeverSlept();
});

it('accepts the same refund twice', function () {
    $gateway = new FakePaymentGateway(0.0);
    $refund = (new Refund)->forceFill(['id' => 1]);

    $gateway->refund($refund);
    $gateway->refund($refund);
})->throwsNoExceptions();
