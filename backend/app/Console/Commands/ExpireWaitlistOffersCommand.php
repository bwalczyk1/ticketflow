<?php

namespace App\Console\Commands;

use App\Services\Waitlist\ExpireWaitlistOffers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ticketflow:expire-waitlist-offers')]
#[Description('Expire waitlist offers that were not accepted in time and offer their seats to the next in line')]
class ExpireWaitlistOffersCommand extends Command
{
    public function handle(ExpireWaitlistOffers $expireWaitlistOffers): int
    {
        $this->info("Expired {$expireWaitlistOffers->handle()} waitlist offers.");

        return self::SUCCESS;
    }
}
