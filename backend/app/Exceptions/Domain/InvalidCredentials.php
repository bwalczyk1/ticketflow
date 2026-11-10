<?php

namespace App\Exceptions\Domain;

class InvalidCredentials extends DomainException
{
    public function __construct(string $message = 'Invalid email or password')
    {
        parent::__construct($message, 'INVALID_CREDENTIALS', 401);
    }
}
