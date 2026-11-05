<?php

namespace App\Exceptions\Domain;

class SalesClosed extends DomainException
{
    public function __construct(string $message = 'Tickets for this event are not on sale')
    {
        parent::__construct($message, 'SALES_CLOSED', 409);
    }
}
