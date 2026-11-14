<?php

namespace App\Services\Documents;

use Carbon\CarbonInterface;

class InvoiceNumbering
{
    /**
     * The numbering year is the year in the business timezone, not in UTC.
     */
    public static function yearOf(CarbonInterface $at, string $timezone): int
    {
        return (int) $at->copy()->setTimezone($timezone)->format('Y');
    }

    public static function next(int $lastNumber): int
    {
        return $lastNumber + 1;
    }

    /**
     * Numbers restart every year, so only the year and the number together identify an invoice.
     */
    public static function label(int $year, int $number): string
    {
        return "{$year}/{$number}";
    }
}
