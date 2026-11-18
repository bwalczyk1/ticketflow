<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Exceptions\Domain\RefundWindowClosed;
use App\Services\Refunds\RefundEligibility;
use Carbon\CarbonImmutable;

function startsAt(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-12-10 19:00:00', 'Europe/Warsaw');
}

it('closes the refund window 48 hours before the start', function () {
    expect(RefundEligibility::refundableUntil(startsAt(), 48)->utc()->toIso8601ZuluString())->toBe('2026-12-08T18:00:00Z');
});

it('allows a refund up to and including the deadline', function (string $before) {
    RefundEligibility::ensureRefundable(OrderStatus::Paid, startsAt(), startsAt()->sub($before), 48);
})->with([
    '48 h 01 min before' => ['48 hours 1 minute'],
    'exactly 48 h before' => ['48 hours'],
])->throwsNoExceptions();

it('refuses a refund after the deadline', function (string $before) {
    RefundEligibility::ensureRefundable(OrderStatus::Paid, startsAt(), startsAt()->sub($before), 48);
})->with([
    '47 h 59 min before' => ['47 hours 59 minutes'],
    'one second after the deadline' => ['47 hours 59 minutes 59 seconds'],
    'after the start' => ['-1 hour'],
])->throws(RefundWindowClosed::class);

it('counts 48 real hours across the change to winter time', function () {
    // Clocks in Warsaw go back on 25 October 2026, so the local wall clock shows only 47 hours here.
    $startsAt = CarbonImmutable::parse('2026-10-26 19:00:00', 'Europe/Warsaw');
    $deadline = CarbonImmutable::parse('2026-10-24 20:00:00', 'Europe/Warsaw');

    expect(RefundEligibility::refundableUntil($startsAt, 48)->equalTo($deadline))->toBeTrue();

    RefundEligibility::ensureRefundable(OrderStatus::Paid, $startsAt, $deadline, 48);

    expect(fn () => RefundEligibility::ensureRefundable(OrderStatus::Paid, $startsAt, $deadline->addSecond(), 48))
        ->toThrow(RefundWindowClosed::class);
});

it('gives the same deadline whatever time zone the start is written in', function () {
    expect(RefundEligibility::refundableUntil(startsAt()->utc(), 48)->equalTo(RefundEligibility::refundableUntil(startsAt(), 48)))->toBeTrue();
});

it('refunds only paid orders', function (OrderStatus $status) {
    RefundEligibility::ensureRefundable($status, startsAt(), startsAt()->subDays(10), 48);
})->with([
    OrderStatus::Pending,
    OrderStatus::Expired,
    OrderStatus::Failed,
    OrderStatus::Cancelled,
    OrderStatus::Refunded,
])->throws(OrderNotRefundable::class);

it('checks the status before the deadline', function () {
    RefundEligibility::ensureRefundable(OrderStatus::Refunded, startsAt(), startsAt(), 48);
})->throws(OrderNotRefundable::class);
