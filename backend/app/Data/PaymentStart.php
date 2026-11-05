<?php

namespace App\Data;

readonly class PaymentStart
{
    public function __construct(
        public ?string $providerRef = null,
        public ?string $redirectUrl = null,
    ) {
    }
}
