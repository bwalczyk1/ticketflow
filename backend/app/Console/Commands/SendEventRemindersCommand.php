<?php

namespace App\Console\Commands;

use App\Services\Events\SendEventReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ticketflow:send-event-reminders')]
#[Description('Queue reminder e-mails for attendees of events that start in 24 to 25 hours')]
class SendEventRemindersCommand extends Command
{
    public function handle(SendEventReminders $sendEventReminders): int
    {
        $this->info("Sent reminders for {$sendEventReminders->handle()} events.");

        return self::SUCCESS;
    }
}
