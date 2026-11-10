<?php

namespace App\Exceptions\Domain;

class EventClosed extends DomainException
{
    public function __construct(string $message = 'A finished or cancelled event cannot be changed')
    {
        parent::__construct($message, 'EVENT_CLOSED', 409);
    }
}
