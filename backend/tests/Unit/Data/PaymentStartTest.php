<?php

use App\Data\PaymentStart;

it('has no provider reference or redirect by default', function () {
    $start = new PaymentStart;

    expect($start->providerRef)->toBeNull()
        ->and($start->redirectUrl)->toBeNull();
});

it('keeps the values of a redirect gateway', function () {
    $start = new PaymentStart(providerRef: 'cs_1', redirectUrl: 'https://pay.example.com/cs_1');

    expect($start->providerRef)->toBe('cs_1')
        ->and($start->redirectUrl)->toBe('https://pay.example.com/cs_1');
});
