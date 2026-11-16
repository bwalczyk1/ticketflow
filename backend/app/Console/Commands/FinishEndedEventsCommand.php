<?php

namespace App\Console\Commands;

use App\Services\Events\FinishEndedEvents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ticketflow:finish-events')]
#[Description('Move published events to FINISHED after their end')]
class FinishEndedEventsCommand extends Command
{
    public function handle(FinishEndedEvents $finishEndedEvents): int
    {
        $this->info("Finished {$finishEndedEvents->handle()} events.");

        return self::SUCCESS;
    }
}
