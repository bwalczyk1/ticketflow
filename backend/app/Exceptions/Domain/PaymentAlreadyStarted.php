<?php

namespace App\Exceptions\Domain;

class PaymentAlreadyStarted extends DomainException
{
    public function __construct(string $message = 'Payment for this order has already started')
    {
        parent::__construct($message, 'PAYMENT_ALREADY_STARTED', 409);
    }
}
