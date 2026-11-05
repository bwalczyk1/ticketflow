<?php

namespace App\Exceptions\Domain;

class TicketsSoldOut extends DomainException
{
    public function __construct(string $message = 'Not enough tickets available')
    {
        parent::__construct($message, 'TICKETS_SOLD_OUT', 409);
    }
}
