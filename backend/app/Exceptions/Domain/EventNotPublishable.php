<?php

namespace App\Exceptions\Domain;

class EventNotPublishable extends DomainException
{
    public function __construct(string $message = 'This event cannot be published yet')
    {
        parent::__construct($message, 'EVENT_NOT_PUBLISHABLE', 409);
    }
}
