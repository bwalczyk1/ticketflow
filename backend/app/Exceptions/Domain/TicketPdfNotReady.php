<?php

namespace App\Exceptions\Domain;

class TicketPdfNotReady extends DomainException
{
    public function __construct(string $message = 'The ticket PDF is still being generated, try again in a moment')
    {
        parent::__construct($message, 'TICKET_PDF_NOT_READY', 409);
    }
}
