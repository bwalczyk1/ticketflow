<?php

namespace App\Listeners\Waitlist;

use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Services\Waitlist\CloseEventWaitlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;

#[Queue(QueueName::Waitlist)]
class CloseWaitlist implements ShouldQueue
{
    public function __construct(private CloseEventWaitlist $closeEventWaitlist) {}

    public function handle(EventCancelled $event): void
    {
        $this->closeEventWaitlist->handle($event->eventId);
    }
}
