<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Exceptions\Domain\EventClosed;
use App\Exceptions\Domain\EventHasSales;
use App\Exceptions\Domain\EventNotPublishable;
use App\Exceptions\Domain\InvalidEventDates;
use App\Exceptions\Domain\InvalidStatusTransition;
use Carbon\CarbonImmutable;

class EventPublication
{
    /**
     * @throws InvalidStatusTransition
     * @throws EventNotPublishable
     */
    public static function ensurePublishable(EventStatus $status, int $ticketTypes, CarbonImmutable $startsAt, CarbonImmutable $now): void
    {
        if (! $status->canTransitionTo(EventStatus::Published)) {
            throw new InvalidStatusTransition;
        }

        if ($ticketTypes === 0) {
            throw new EventNotPublishable('An event needs at least one ticket type before publishing');
        }

        if ($startsAt <= $now) {
            throw new EventNotPublishable('An event must start in the future to be published');
        }
    }

    /**
     * @throws EventHasSales
     */
    public static function ensureDeletable(int $sold, int $held): void
    {
        if ($sold + $held > 0) {
            throw new EventHasSales;
        }
    }

    /**
     * @throws InvalidEventDates
     */
    public static function ensureDatesInOrder(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($endsAt <= $startsAt) {
            throw new InvalidEventDates;
        }
    }

    /**
     * @throws EventClosed
     */
    public static function ensureEditable(EventStatus $status): void
    {
        if ($status === EventStatus::Finished || $status === EventStatus::Cancelled) {
            throw new EventClosed;
        }
    }
}
