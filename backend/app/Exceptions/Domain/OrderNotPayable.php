<?php

namespace App\Exceptions\Domain;

class OrderNotPayable extends DomainException
{
    public function __construct(string $message = 'This order cannot be paid in its current status')
    {
        parent::__construct($message, 'ORDER_NOT_PAYABLE', 409);
    }
}
