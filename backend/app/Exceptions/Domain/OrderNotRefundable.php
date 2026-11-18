<?php

namespace App\Exceptions\Domain;

class OrderNotRefundable extends DomainException
{
    public function __construct(string $message = 'Only a paid order can be refunded')
    {
        parent::__construct($message, 'ORDER_NOT_REFUNDABLE', 409);
    }
}
