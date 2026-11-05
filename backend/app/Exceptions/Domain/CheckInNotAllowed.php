<?php

namespace App\Exceptions\Domain;

class CheckInNotAllowed extends DomainException
{
    public function __construct(string $message = 'This ticket cannot be checked in')
    {
        parent::__construct($message, 'CHECK_IN_NOT_ALLOWED', 409);
    }
}
