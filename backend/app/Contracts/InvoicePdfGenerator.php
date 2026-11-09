<?php

namespace App\Contracts;

use App\Models\Invoice;

interface InvoicePdfGenerator
{
    /**
     * Returns the storage path invoices/{year}/{type}-{number}.pdf.
     */
    public function generate(Invoice $invoice): string;
}
