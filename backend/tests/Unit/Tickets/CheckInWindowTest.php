<?php

use App\Enums\EventStatus;
use App\Exceptions\Domain\CheckInClosed;
use App\Services\Tickets\CheckInWindow;
use Carbon\CarbonImmutable;

function checkInOpen(string $startsAt, string $endsAt, string $now, string $timezone = 'Europe/Warsaw', EventStatus $status = EventStatus::Published): bool
{
    return CheckInWindow::isOpen(
        $status,
        CarbonImmutable::parse($startsAt, 'UTC'),
        CarbonImmutable::parse($endsAt, 'UTC'),
        $timezone,
        CarbonImmutable::parse($now, 'UTC'),
    );
}

it('opens on the local day of the event', function (string $now, bool $open) {
    // 19:00-22:00 in Warsaw on 20 November is 18:00-21:00 UTC.
    expect(checkInOpen('2026-11-20 18:00:00', '2026-11-20 21:00:00', $now))->toBe($open);
})->with([
    'local midnight that starts the day' => ['2026-11-19 23:00:00', true],
    'one second before the local day' => ['2026-11-19 22:59:59', false],
    'during the event' => ['2026-11-20 19:30:00', true],
    'after the end on the same day' => ['2026-11-20 22:59:59', true],
    'local midnight after the day' => ['2026-11-20 23:00:00', false],
]);

it('takes the day from the event time zone, not from UTC', function () {
    // 00:30 in Warsaw on 21 November is still 20 November in UTC.
    $startsAt = '2026-11-20 23:30:00';
    $endsAt = '2026-11-21 01:00:00';

    expect(checkInOpen($startsAt, $endsAt, '2026-11-20 22:59:59'))->toBeFalse()
        ->and(checkInOpen($startsAt, $endsAt, '2026-11-20 23:00:00'))->toBeTrue()
        ->and(checkInOpen($startsAt, $endsAt, '2026-11-20 12:00:00'))->toBeFalse()
        ->and(checkInOpen($startsAt, $endsAt, '2026-11-20 12:00:00', 'UTC'))->toBeTrue();
});

it('keeps the day boundaries on the day the clocks go forward', function (string $now, bool $open) {
    // 29 March 2026 has 23 hours in Warsaw: it starts at 23:00 UTC (CET) and ends at 22:00 UTC (CEST).
    expect(checkInOpen('2026-03-29 18:00:00', '2026-03-29 20:00:00', $now))->toBe($open);
})->with([
    ['2026-03-28 22:59:59', false],
    ['2026-03-28 23:00:00', true],
    ['2026-03-29 21:59:59', true],
    ['2026-03-29 22:00:00', false],
]);

it('stays open until the local day on which the event ends', function () {
    // 22:00 on 20 November until 02:00 on 21 November in Warsaw.
    $startsAt = '2026-11-20 21:00:00';
    $endsAt = '2026-11-21 01:00:00';

    expect(checkInOpen($startsAt, $endsAt, '2026-11-21 00:30:00'))->toBeTrue()
        ->and(checkInOpen($startsAt, $endsAt, '2026-11-21 22:59:59'))->toBeTrue()
        ->and(checkInOpen($startsAt, $endsAt, '2026-11-21 23:00:00'))->toBeFalse();
});

it('opens a finished event until the end of its day', function () {
    expect(checkInOpen('2026-11-20 18:00:00', '2026-11-20 21:00:00', '2026-11-20 22:00:00', status: EventStatus::Finished))->toBeTrue();
});

it('stays closed for an event that is not taking place', function (EventStatus $status) {
    expect(checkInOpen('2026-11-20 18:00:00', '2026-11-20 21:00:00', '2026-11-20 18:30:00', status: $status))->toBeFalse();
})->with([EventStatus::Draft, EventStatus::Cancelled]);

it('throws CHECK_IN_CLOSED outside the day', function () {
    expect(fn () => CheckInWindow::ensureOpen(
        EventStatus::Published,
        CarbonImmutable::parse('2026-11-20 18:00:00', 'UTC'),
        CarbonImmutable::parse('2026-11-20 21:00:00', 'UTC'),
        'Europe/Warsaw',
        CarbonImmutable::parse('2026-11-21 08:00:00', 'UTC'),
    ))->toThrow(CheckInClosed::class);
});
