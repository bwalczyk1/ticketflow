<?php

use App\Support\ConfigValidator;

function validConfig(array $overrides = []): array
{
    return array_merge([
        'app_key' => 'base64:key',
        'payment_gateway' => 'fake',
        'fake_failure_rate' => 0.1,
    ], $overrides);
}

it('accepts a complete configuration', function () {
    expect(ConfigValidator::errors(validConfig()))->toBe([]);
});

it('reports a missing application key', function () {
    expect(ConfigValidator::errors(validConfig(['app_key' => ''])))
        ->toContain('APP_KEY must be set');
});

it('reports an unknown payment gateway', function () {
    expect(ConfigValidator::errors(validConfig(['payment_gateway' => 'cash'])))
        ->toContain('PAYMENT_GATEWAY must be one of: fake, stripe');
});

it('reports a failure rate outside the 0-1 range', function (mixed $rate) {
    expect(ConfigValidator::errors(validConfig(['fake_failure_rate' => $rate])))
        ->toContain('FAKE_PAYMENT_FAILURE_RATE must be a number between 0 and 1');
})->with([-0.1, 1.1, 'often']);

it('requires stripe keys only for the stripe gateway', function () {
    expect(ConfigValidator::errors(validConfig(['payment_gateway' => 'stripe', 'stripe_secret' => ''])))
        ->toContain('STRIPE_SECRET must be set when PAYMENT_GATEWAY is stripe');
});
