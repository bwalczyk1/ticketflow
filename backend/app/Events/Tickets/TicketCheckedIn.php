<?php

namespace App\Events\Tickets;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TicketCheckedIn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $ticketId) {}
}
