<?php

namespace App\Exceptions\Domain;

class PoolProtected extends DomainException
{
    public function __construct(string $message = 'Ticket pool cannot go below sold and held tickets')
    {
        parent::__construct($message, 'POOL_PROTECTED', 409);
    }
}
