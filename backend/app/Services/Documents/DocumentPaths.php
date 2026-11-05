<?php

namespace App\Services\Documents;

use App\Enums\InvoiceType;

class DocumentPaths
{
    public static function ticket(string $code): string
    {
        return "tickets/{$code}.pdf";
    }

    public static function invoice(int $year, InvoiceType $type, int $number): string
    {
        return "invoices/{$year}/".strtolower($type->value)."-{$number}.pdf";
    }
}
