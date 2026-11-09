<?php

use App\Support\ApiDate;
use Carbon\CarbonImmutable;

it('formats dates as UTC with a Z suffix', function () {
    $date = CarbonImmutable::parse('2026-12-01 19:00:00', 'Europe/Warsaw');

    expect(ApiDate::format($date))->toBe('2026-12-01T18:00:00Z');
});

it('keeps null as null', function () {
    expect(ApiDate::format(null))->toBeNull();
});
