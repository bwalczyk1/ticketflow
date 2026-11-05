<?php

namespace App\Events\Orders;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ReservationExpired implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $orderId)
    {
    }
}
