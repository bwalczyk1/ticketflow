<?php

use App\Enums\EventStatus;
use App\Exceptions\Domain\SalesClosed;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Exceptions\Domain\TicketsSoldOut;
use App\Services\Orders\TicketAvailability;
use Carbon\CarbonImmutable;

it('counts free seats as quantity minus sold minus held', function (int $quantity, int $sold, int $held, int $free) {
    expect(TicketAvailability::free($quantity, $sold, $held))->toBe($free);
})->with([
    'untouched pool' => [10, 0, 0, 10],
    'partly sold and held' => [10, 3, 2, 5],
    'nothing left' => [10, 6, 4, 0],
]);

it('allows a request for exactly the free seats', function () {
    TicketAvailability::ensureAvailable(5, 10, 3, 2);
})->throwsNoExceptions();

it('refuses a request above the free seats', function (int $requested, int $quantity, int $sold, int $held) {
    TicketAvailability::ensureAvailable($requested, $quantity, $sold, $held);
})->with([
    'one too many' => [6, 10, 3, 2],
    'nothing free' => [1, 10, 6, 4],
])->throws(TicketsSoldOut::class);

it('allows tickets up to the limit including active orders', function (int $requested, int $active) {
    TicketAvailability::ensureWithinLimit($requested, $active, 6);
})->with([
    'first order' => [6, 0],
    'exactly on the limit' => [2, 4],
])->throwsNoExceptions();

it('refuses tickets above the limit including active orders', function (int $requested, int $active) {
    TicketAvailability::ensureWithinLimit($requested, $active, 6);
})->with([
    'one order above the limit' => [7, 0],
    'one above with active orders' => [3, 4],
    'limit already used' => [1, 6],
])->throws(TicketLimitExceeded::class);

it('sells tickets for a published event before it starts', function () {
    $now = CarbonImmutable::parse('2026-12-01 18:59:59');

    TicketAvailability::ensureOnSale(EventStatus::Published, CarbonImmutable::parse('2026-12-01 19:00:00'), $now);
})->throwsNoExceptions();

it('refuses sales outside the sale window', function (EventStatus $status, string $now) {
    TicketAvailability::ensureOnSale($status, CarbonImmutable::parse('2026-12-01 19:00:00'), CarbonImmutable::parse($now));
})->with([
    'draft' => [EventStatus::Draft, '2026-11-01 12:00:00'],
    'cancelled' => [EventStatus::Cancelled, '2026-11-01 12:00:00'],
    'finished' => [EventStatus::Finished, '2026-12-02 12:00:00'],
    'exactly at the start' => [EventStatus::Published, '2026-12-01 19:00:00'],
    'after the start' => [EventStatus::Published, '2026-12-01 19:00:01'],
])->throws(SalesClosed::class);

it('sets the hold end the given number of minutes ahead', function () {
    $now = CarbonImmutable::parse('2026-11-06 12:00:00');

    expect(TicketAvailability::holdEndsAt($now, 15)->toDateTimeString())->toBe('2026-11-06 12:15:00');
});

it('treats a hold as ended from the exact second of expires_at', function (string $now, bool $ended) {
    $expiresAt = CarbonImmutable::parse('2026-11-06 12:15:00');

    expect(TicketAvailability::holdEnded($expiresAt, CarbonImmutable::parse($now)))->toBe($ended);
})->with([
    'one second before' => ['2026-11-06 12:14:59', false],
    'exactly on the boundary' => ['2026-11-06 12:15:00', true],
    'one second after' => ['2026-11-06 12:15:01', true],
]);

it('measures the hold in absolute time across a clock change', function () {
    $beforeTheChange = CarbonImmutable::parse('2026-10-25 00:50:00', 'UTC')->setTimezone('Europe/Warsaw');

    expect(TicketAvailability::holdEndsAt($beforeTheChange, 15)->utc()->toDateTimeString())
        ->toBe('2026-10-25 01:05:00');
});
