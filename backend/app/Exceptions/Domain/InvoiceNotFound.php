<?php

namespace App\Exceptions\Domain;

class InvoiceNotFound extends DomainException
{
    public function __construct(string $message = 'This order has no invoice yet')
    {
        parent::__construct($message, 'INVOICE_NOT_FOUND', 404);
    }
}
