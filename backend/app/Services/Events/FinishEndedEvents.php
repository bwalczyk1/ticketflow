<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Models\Event;

class FinishEndedEvents
{
    /**
     * One conditional update, so an event cancelled in the meantime keeps its status.
     */
    public function handle(): int
    {
        return Event::query()
            ->whereIn('status', self::finishableStatuses())
            ->where('ends_at', '<', now())
            ->update(['status' => EventStatus::Finished]);
    }

    /**
     * @return list<EventStatus>
     */
    public static function finishableStatuses(): array
    {
        return array_values(array_filter(
            EventStatus::cases(),
            fn (EventStatus $status) => $status->canTransitionTo(EventStatus::Finished),
        ));
    }
}
