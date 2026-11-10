<?php

namespace App\Services\Events;

use App\Exceptions\Domain\PoolProtected;

class PoolProtection
{
    /**
     * @throws PoolProtected
     */
    public static function ensureQuantity(int $quantity, int $sold, int $held): void
    {
        if ($quantity < $sold + $held) {
            throw new PoolProtected;
        }
    }
}
