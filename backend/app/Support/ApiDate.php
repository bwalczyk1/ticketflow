<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ApiDate
{
    public static function format(?CarbonInterface $date): ?string
    {
        return $date?->toImmutable()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
