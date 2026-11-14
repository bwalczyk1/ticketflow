<?php

namespace App\Exceptions\Domain;

class WaitlistNotNeeded extends DomainException
{
    public function __construct(string $message = 'Enough tickets are still available, so they can be bought right away')
    {
        parent::__construct($message, 'WAITLIST_NOT_NEEDED', 409);
    }
}
