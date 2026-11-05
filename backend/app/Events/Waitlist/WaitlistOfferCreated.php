<?php

namespace App\Events\Waitlist;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class WaitlistOfferCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $waitlistEntryId)
    {
    }
}
