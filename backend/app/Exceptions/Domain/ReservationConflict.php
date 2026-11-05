<?php

namespace App\Exceptions\Domain;

class ReservationConflict extends DomainException
{
    public function __construct(string $message = 'Too many concurrent reservations, please try again')
    {
        parent::__construct($message, 'RESERVATION_CONFLICT', 409);
    }
}
