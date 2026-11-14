<?php

use App\Enums\EventStatus;
use App\Exceptions\Domain\EventClosed;
use App\Exceptions\Domain\EventHasSales;
use App\Exceptions\Domain\EventNotPublishable;
use App\Exceptions\Domain\InvalidEventDates;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Services\Events\EventPublication;
use Carbon\CarbonImmutable;

it('requires at least one ticket type before publishing', function () {
    $now = CarbonImmutable::parse('2026-12-01T12:00:00Z');

    EventPublication::ensurePublishable(EventStatus::Draft, 0, $now->addDay(), $now);
})->throws(EventNotPublishable::class, 'at least one ticket type');

it('requires a start date in the future before publishing', function (string $startsAt) {
    $now = CarbonImmutable::parse('2026-12-01T12:00:00Z');

    EventPublication::ensurePublishable(EventStatus::Draft, 1, CarbonImmutable::parse($startsAt), $now);
})->with([
    'exactly now' => ['2026-12-01T12:00:00Z'],
    'in the past' => ['2026-12-01T11:59:59Z'],
])->throws(EventNotPublishable::class, 'in the future');

it('allows publishing a draft with a ticket type that starts in the future', function () {
    $now = CarbonImmutable::parse('2026-12-01T12:00:00Z');

    EventPublication::ensurePublishable(EventStatus::Draft, 1, $now->addSecond(), $now);
})->throwsNoExceptions();

it('checks the status before anything else when publishing', function (EventStatus $status) {
    $now = CarbonImmutable::parse('2026-12-01T12:00:00Z');

    EventPublication::ensurePublishable($status, 0, $now->subDay(), $now);
})->with([EventStatus::Published, EventStatus::Finished, EventStatus::Cancelled])
    ->throws(InvalidStatusTransition::class);

it('refuses to delete an event with sales', function (int $sold, int $held) {
    EventPublication::ensureDeletable($sold, $held);
})->with([
    'sold tickets' => [1, 0],
    'held tickets' => [0, 1],
])->throws(EventHasSales::class);

it('allows deleting an event without sales', function () {
    EventPublication::ensureDeletable(0, 0);
})->throwsNoExceptions();

it('requires the end after the start', function (string $endsAt) {
    EventPublication::ensureDatesInOrder(CarbonImmutable::parse('2026-12-01T12:00:00Z'), CarbonImmutable::parse($endsAt));
})->with([
    'equal' => ['2026-12-01T12:00:00Z'],
    'earlier' => ['2026-12-01T11:59:59Z'],
])->throws(InvalidEventDates::class);

it('requires a new start in the future', function (string $startsAt) {
    EventPublication::ensureStartsInFuture(CarbonImmutable::parse($startsAt), CarbonImmutable::parse('2026-12-01T12:00:00Z'));
})->with([
    'exactly now' => ['2026-12-01T12:00:00Z'],
    'in the past' => ['2026-12-01T11:59:59Z'],
])->throws(InvalidEventDates::class);

it('accepts a new start one second from now', function () {
    EventPublication::ensureStartsInFuture(CarbonImmutable::parse('2026-12-01T12:00:01Z'), CarbonImmutable::parse('2026-12-01T12:00:00Z'));
})->throwsNoExceptions();

it('accepts an end after the start', function () {
    EventPublication::ensureDatesInOrder(CarbonImmutable::parse('2026-12-01T12:00:00Z'), CarbonImmutable::parse('2026-12-01T12:00:01Z'));
})->throwsNoExceptions();

it('refuses changes to a closed event', function (EventStatus $status) {
    EventPublication::ensureEditable($status);
})->with([EventStatus::Finished, EventStatus::Cancelled])
    ->throws(EventClosed::class);

it('allows changes to an open event', function (EventStatus $status) {
    EventPublication::ensureEditable($status);
})->with([EventStatus::Draft, EventStatus::Published])
    ->throwsNoExceptions();
