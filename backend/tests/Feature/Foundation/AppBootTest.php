<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

function bootProvider(): void
{
    (new AppServiceProvider(app()))->boot();
}

it('boots with a valid configuration', function () {
    bootProvider();

    expect(true)->toBeTrue();
});

it('stops the application on invalid configuration and lists every error', function () {
    config(['app.key' => '', 'ticketflow.fake_failure_rate' => 5]);

    expect(fn () => bootProvider())->toThrow(function (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('APP_KEY must be set')
            ->toContain('FAKE_PAYMENT_FAILURE_RATE must be a number between 0 and 1');
    });
});

it('requires the key of the selected gateway', function () {
    config(['ticketflow.payment_gateway' => 'stripe', 'ticketflow.stripe_secret' => null]);

    expect(fn () => bootProvider())->toThrow(RuntimeException::class, 'STRIPE_SECRET must be set');
});

it('lets setup commands run before the configuration is complete', function (string $command) {
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', $command];
    config(['app.key' => '']);

    try {
        bootProvider();
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect(true)->toBeTrue();
})->with(['key:generate', 'package:discover']);

it('fails on lazy loading outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('returns resources without a data wrapper', function () {
    expect(JsonResource::$wrap)->toBeNull();
});
