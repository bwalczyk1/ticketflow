<?php

namespace App\Contracts;

use App\Models\Ticket;

interface TicketPdfGenerator
{
    /**
     * Returns the storage path tickets/{code}.pdf.
     */
    public function generate(Ticket $ticket): string;
}
