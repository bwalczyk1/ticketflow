<?php

namespace App\Exceptions\Domain;

class InvalidStatusTransition extends DomainException
{
    public function __construct(string $message = 'This status change is not allowed')
    {
        parent::__construct($message, 'INVALID_STATUS_TRANSITION', 409);
    }
}
