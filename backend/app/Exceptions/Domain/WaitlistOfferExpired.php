<?php

namespace App\Exceptions\Domain;

class WaitlistOfferExpired extends DomainException
{
    public function __construct(string $message = 'The waitlist offer has expired')
    {
        parent::__construct($message, 'WAITLIST_OFFER_EXPIRED', 410);
    }
}
