<?php

namespace App\Services\Events;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Which events an hourly reminder run picks up: those starting 24 to 25 hours from now.
 */
class ReminderWindow
{
    private const FROM_HOURS = 24;

    private const TO_HOURS = 25;

    /**
     * A half-open hour counted in UTC, so consecutive hourly runs cover the time without gaps or
     * overlaps and a clock change does not move the window.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(CarbonInterface $now): array
    {
        $now = $now->toImmutable()->utc();

        return [$now->addHours(self::FROM_HOURS), $now->addHours(self::TO_HOURS)];
    }

    public static function contains(CarbonInterface $startsAt, CarbonInterface $now): bool
    {
        [$from, $to] = self::bounds($now);

        return $startsAt >= $from && $startsAt < $to;
    }
}
