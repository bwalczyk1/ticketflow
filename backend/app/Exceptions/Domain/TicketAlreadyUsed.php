<?php

namespace App\Exceptions\Domain;

class TicketAlreadyUsed extends DomainException
{
    public function __construct(string $message = 'This ticket has already been used')
    {
        parent::__construct($message, 'TICKET_ALREADY_USED', 409);
    }
}
