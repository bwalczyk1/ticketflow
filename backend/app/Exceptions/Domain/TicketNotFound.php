<?php

namespace App\Exceptions\Domain;

class TicketNotFound extends DomainException
{
    public function __construct(string $message = 'No ticket of this event has this code')
    {
        parent::__construct($message, 'TICKET_NOT_FOUND', 404);
    }
}
