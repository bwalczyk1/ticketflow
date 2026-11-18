<?php

namespace App\Services\Orders;

use App\Enums\EventStatus;
use App\Exceptions\Domain\SalesClosed;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Exceptions\Domain\TicketsSoldOut;
use Carbon\CarbonInterface;

class TicketAvailability
{
    public static function free(int $quantity, int $sold, int $held): int
    {
        return $quantity - $sold - $held;
    }

    /**
     * @throws TicketsSoldOut
     */
    public static function ensureAvailable(int $requested, int $quantity, int $sold, int $held): void
    {
        if ($requested > self::free($quantity, $sold, $held)) {
            throw new TicketsSoldOut;
        }
    }

    /**
     * @throws TicketLimitExceeded
     */
    public static function ensureWithinLimit(int $requested, int $activeTickets, int $limit): void
    {
        if ($requested + $activeTickets > $limit) {
            throw new TicketLimitExceeded;
        }
    }

    public static function isOnSale(EventStatus $status, CarbonInterface $startsAt, CarbonInterface $now): bool
    {
        return $status === EventStatus::Published && $now < $startsAt;
    }

    /**
     * @throws SalesClosed
     */
    public static function ensureOnSale(EventStatus $status, CarbonInterface $startsAt, CarbonInterface $now): void
    {
        if (! self::isOnSale($status, $startsAt, $now)) {
            throw new SalesClosed;
        }
    }
}
