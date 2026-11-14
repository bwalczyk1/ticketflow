<?php

namespace App\Exceptions\Domain;

class RefundWindowClosed extends DomainException
{
    public function __construct(string $message = 'Refunds close 48 hours before the event starts')
    {
        parent::__construct($message, 'REFUND_WINDOW_CLOSED', 409);
    }
}
