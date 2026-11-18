<?php

namespace App\Exceptions\Domain;

class PaymentDeclined extends DomainException
{
    public function __construct(string $message = 'The payment was declined')
    {
        parent::__construct($message, 'PAYMENT_DECLINED', 402);
    }
}
