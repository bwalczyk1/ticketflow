<?php

namespace App\Events\Catalog;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class EventCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $eventId) {}
}
