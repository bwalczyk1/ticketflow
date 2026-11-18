<?php

namespace App\Exceptions\Domain;

class TicketVoid extends DomainException
{
    public function __construct(string $message = 'This ticket is void')
    {
        parent::__construct($message, 'TICKET_VOID', 409);
    }
}
