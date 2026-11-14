<?php

namespace App\Exceptions\Domain;

class WaitlistOfferNotActive extends DomainException
{
    public function __construct(string $message = 'This waitlist entry has no open offer')
    {
        parent::__construct($message, 'WAITLIST_OFFER_NOT_ACTIVE', 409);
    }
}
