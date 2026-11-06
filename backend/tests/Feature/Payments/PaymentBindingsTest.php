<?php

use App\Contracts\PaymentGateway;
use App\Exceptions\Domain\PaymentDeclined;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Sleep;

it('uses the fake gateway from the configuration', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('fails clearly for a gateway without an implementation', function () {
    config(['ticketflow.payment_gateway' => 'stripe']);

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class, 'stripe');
});

it('honours FAKE_PAYMENT_FAILURE_RATE', function () {
    $payment = Payment::factory()->create();

    config(['ticketflow.fake_failure_rate' => '0']);

    expect(app(PaymentGateway::class)->startPayment($payment)->providerRef)->not->toBeNull();

    config(['ticketflow.fake_failure_rate' => '1']);

    expect(fn () => app(PaymentGateway::class)->startPayment($payment))->toThrow(PaymentDeclined::class);
});

it('takes 1 to 3 seconds outside tests', function () {
    Sleep::fake();
    config(['ticketflow.fake_failure_rate' => '0']);
    app()->detectEnvironment(fn () => 'local');

    app(PaymentGateway::class)->startPayment(Payment::factory()->create());

    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 1000 && $duration->totalMilliseconds <= 3000);
});

it('does not wait in tests', function () {
    Sleep::fake();
    config(['ticketflow.fake_failure_rate' => '0']);

    app(PaymentGateway::class)->startPayment(Payment::factory()->create());

    Sleep::assertNeverSlept();
});
