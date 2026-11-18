<?php

namespace App\Exceptions\Domain;

class AlreadyOnWaitlist extends DomainException
{
    public function __construct(string $message = 'You are already on the waitlist of this pool')
    {
        parent::__construct($message, 'ALREADY_ON_WAITLIST', 409);
    }
}
