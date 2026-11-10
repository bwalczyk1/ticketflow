<?php

namespace App\Exceptions\Domain;

class EventHasSales extends DomainException
{
    public function __construct(string $message = 'An event with sold or held tickets can only be cancelled')
    {
        parent::__construct($message, 'EVENT_HAS_SALES', 409);
    }
}
