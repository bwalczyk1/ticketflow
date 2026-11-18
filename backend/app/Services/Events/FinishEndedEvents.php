<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Events\Catalog\EventFinished;
use App\Models\Event;

class FinishEndedEvents
{
    /**
     * Every event moves by its own conditional update, so an event cancelled in the meantime keeps
     * its status, and only the run that finished an event announces it.
     */
    public function handle(): int
    {
        return Event::query()
            ->whereIn('status', self::finishableStatuses())
            ->where('ends_at', '<', now())
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $eventId) => $this->finish($eventId))
            ->count();
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

    private function finish(int $eventId): bool
    {
        $finished = Event::query()
            ->whereKey($eventId)
            ->whereIn('status', self::finishableStatuses())
            ->update(['status' => EventStatus::Finished]);

        if ($finished === 0) {
            return false;
        }

        EventFinished::dispatch($eventId);

        return true;
    }
}
