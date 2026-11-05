<?php

namespace App\Exceptions\Domain;

class TicketLimitExceeded extends DomainException
{
    public function __construct(string $message = 'Ticket limit per person exceeded')
    {
        parent::__construct($message, 'TICKET_LIMIT_EXCEEDED', 409);
    }
}
