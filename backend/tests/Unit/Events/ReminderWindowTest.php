<?php

use App\Services\Events\ReminderWindow;
use Carbon\CarbonImmutable;

it('reminds about events that start in 24 to 25 hours', function (string $startsAt, bool $inWindow) {
    $now = CarbonImmutable::parse('2026-11-19 18:00:00', 'UTC');

    expect(ReminderWindow::contains(CarbonImmutable::parse($startsAt, 'UTC'), $now))->toBe($inWindow);
})->with([
    'one second before 24 hours' => ['2026-11-20 17:59:59', false],
    'exactly 24 hours' => ['2026-11-20 18:00:00', true],
    'in the middle' => ['2026-11-20 18:30:00', true],
    'one second before 25 hours' => ['2026-11-20 18:59:59', true],
    'exactly 25 hours' => ['2026-11-20 19:00:00', false],
]);

it('counts the hours absolutely across a clock change', function () {
    // The night of 24/25 October 2026 has 25 hours in Warsaw, so 24 real hours later the local clock shows an hour less.
    $now = CarbonImmutable::parse('2026-10-24 19:00:00', 'Europe/Warsaw');
    [$from, $to] = ReminderWindow::bounds($now);

    expect($from->toIso8601ZuluString())->toBe('2026-10-25T17:00:00Z')
        ->and($to->toIso8601ZuluString())->toBe('2026-10-25T18:00:00Z')
        ->and($from->setTimezone('Europe/Warsaw')->format('H:i'))->toBe('18:00');
});

it('gives each hourly run its own hour, so an event is picked up by exactly one run', function () {
    $startsAt = CarbonImmutable::parse('2026-11-20 18:20:00', 'UTC');

    $runs = collect(range(0, 47))
        ->map(fn (int $hour) => CarbonImmutable::parse('2026-11-18 18:00:00', 'UTC')->addHours($hour))
        ->filter(fn (CarbonImmutable $run) => ReminderWindow::contains($startsAt, $run));

    expect($runs)->toHaveCount(1)
        ->and($runs->first()->toDateTimeString())->toBe('2026-11-19 18:00:00');
});
