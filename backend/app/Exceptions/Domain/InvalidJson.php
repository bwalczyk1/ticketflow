<?php

namespace App\Exceptions\Domain;

class InvalidJson extends DomainException
{
    public function __construct(string $message = 'Malformed JSON body.')
    {
        parent::__construct($message, 'INVALID_JSON', 400);
    }
}
