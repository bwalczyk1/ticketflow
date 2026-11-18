<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'WAITING';
    case Offered = 'OFFERED';
    case Converted = 'CONVERTED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    /**
     * Statuses that still take a place in the queue; the database allows one such entry per user and pool.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Waiting, self::Offered];
    }
}
