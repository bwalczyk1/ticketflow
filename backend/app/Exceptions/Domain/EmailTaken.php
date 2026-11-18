<?php

namespace App\Exceptions\Domain;

class EmailTaken extends DomainException
{
    public function __construct(string $message = 'Email is already registered')
    {
        parent::__construct($message, 'EMAIL_TAKEN', 409);
    }
}
