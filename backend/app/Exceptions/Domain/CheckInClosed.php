<?php

namespace App\Exceptions\Domain;

class CheckInClosed extends DomainException
{
    public function __construct(string $message = 'Check-in is open only on the day of the event')
    {
        parent::__construct($message, 'CHECK_IN_CLOSED', 409);
    }
}
