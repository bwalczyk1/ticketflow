<?php

namespace App\Services\Waitlist;

use App\Exceptions\Domain\WaitlistNotNeeded;

/**
 * The rules of the waitlist, free of the database so they can be tested on their own.
 */
class WaitlistQueue
{
    /**
     * Goes through the queue in the order of joining and gives seats to every entry that still
     * fits. An entry that wants more seats than are left is skipped, not waited for, so a large
     * group at the front does not block single seats for everybody behind it.
     *
     * @param  array<int, int>  $waiting  entry id => quantity, in the order of joining
     * @return list<int> ids of the entries that get an offer
     */
    public static function pick(array $waiting, int $freeSeats): array
    {
        $picked = [];

        foreach ($waiting as $entryId => $quantity) {
            if ($quantity <= $freeSeats) {
                $picked[] = $entryId;
                $freeSeats -= $quantity;
            }
        }

        return $picked;
    }

    /**
     * The queue is for seats that cannot be bought now. With enough free seats the buyer orders them directly.
     *
     * @throws WaitlistNotNeeded
     */
    public static function ensureNeeded(int $requested, int $freeSeats): void
    {
        if ($freeSeats >= $requested) {
            throw new WaitlistNotNeeded;
        }
    }
}
