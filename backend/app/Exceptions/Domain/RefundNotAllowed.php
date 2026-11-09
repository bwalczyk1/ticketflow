<?php

namespace App\Exceptions\Domain;

class RefundNotAllowed extends DomainException
{
    public function __construct(string $message = 'This order can no longer be refunded')
    {
        parent::__construct($message, 'REFUND_NOT_ALLOWED', 409);
    }
}
