<?php

namespace App\Exceptions\Domain;

class ReservationExpired extends DomainException
{
    public function __construct(string $message = 'Reservation has expired')
    {
        parent::__construct($message, 'RESERVATION_EXPIRED', 410);
    }
}
