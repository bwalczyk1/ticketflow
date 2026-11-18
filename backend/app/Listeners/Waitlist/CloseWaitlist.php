<?php

namespace App\Listeners\Waitlist;

use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Services\Waitlist\CloseEventWaitlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloseWaitlist implements ShouldQueue
{
    public function __construct(private CloseEventWaitlist $closeEventWaitlist) {}

    public function viaQueue(): string
    {
        return QueueName::Waitlist->value;
    }

    public function handle(EventCancelled $event): void
    {
        $this->closeEventWaitlist->handle($event->eventId);
    }

    public function failed(EventCancelled $event, Throwable $exception): void
    {
        Log::error('Closing the waitlist of a cancelled event failed', ['event_id' => $event->eventId, 'error' => $exception::class]);
    }
}
