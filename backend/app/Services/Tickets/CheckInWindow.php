<?php

namespace App\Services\Tickets;

use App\Enums\EventStatus;
use App\Exceptions\Domain\CheckInClosed;
use Carbon\CarbonInterface;

class CheckInWindow
{
    /**
     * Check-in is open on the local days of the event, from the midnight that starts the day of
     * starts_at to the midnight that ends the day of ends_at. The days are counted in the event
     * time zone, so a concert at 00:30 in Warsaw opens on its own day, not on the day before in UTC.
     */
    public static function isOpen(EventStatus $status, CarbonInterface $startsAt, CarbonInterface $endsAt, string $timezone, CarbonInterface $now): bool
    {
        if (! in_array($status, [EventStatus::Published, EventStatus::Finished], true)) {
            return false;
        }

        $today = self::localDate($now, $timezone);

        return $today >= self::localDate($startsAt, $timezone) && $today <= self::localDate($endsAt, $timezone);
    }

    /**
     * @throws CheckInClosed
     */
    public static function ensureOpen(EventStatus $status, CarbonInterface $startsAt, CarbonInterface $endsAt, string $timezone, CarbonInterface $now): void
    {
        if (! self::isOpen($status, $startsAt, $endsAt, $timezone, $now)) {
            throw new CheckInClosed;
        }
    }

    private static function localDate(CarbonInterface $moment, string $timezone): string
    {
        return $moment->toImmutable()->setTimezone($timezone)->toDateString();
    }
}
