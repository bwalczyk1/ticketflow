<?php

use App\Enums\InvoiceType;
use App\Services\Documents\InvoiceNumbering;
use Carbon\CarbonImmutable;

const BUSINESS_TIMEZONE = 'Europe/Warsaw';

/**
 * Plays the allocator on an array instead of the invoice_sequences table.
 *
 * @param  array<string, int>  $counters
 */
function allocateInMemory(array &$counters, InvoiceType $type, CarbonImmutable $at): string
{
    $key = InvoiceNumbering::yearOf($at, BUSINESS_TIMEZONE).'/'.$type->value;
    $counters[$key] = InvoiceNumbering::next($counters[$key] ?? 0);

    return $key.'/'.$counters[$key];
}

it('counts the invoice year in the business timezone', function () {
    $at = CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC');

    expect(InvoiceNumbering::yearOf($at, 'Europe/Warsaw'))->toBe(2026)
        ->and(InvoiceNumbering::yearOf($at->setTimezone('Pacific/Auckland'), 'Europe/Warsaw'))->toBe(2026);
});

it('treats 31 December 23:30 UTC as the new year in Warsaw', function () {
    $at = CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC');

    expect($at->year)->toBe(2026)
        ->and(InvoiceNumbering::yearOf($at, 'Europe/Warsaw'))->toBe(2027)
        ->and(InvoiceNumbering::yearOf($at, 'UTC'))->toBe(2026);
});

it('continues numbering within a year', function () {
    $counters = [];
    $at = CarbonImmutable::parse('2026-03-01 12:00:00', 'UTC');

    expect(allocateInMemory($counters, InvoiceType::Invoice, $at))->toBe('2026/INVOICE/1')
        ->and(allocateInMemory($counters, InvoiceType::Invoice, $at->addDays(100)))->toBe('2026/INVOICE/2')
        ->and(allocateInMemory($counters, InvoiceType::Invoice, $at->addDays(200)))->toBe('2026/INVOICE/3');
});

it('starts from 1 in a new year', function () {
    $counters = [];

    allocateInMemory($counters, InvoiceType::Invoice, CarbonImmutable::parse('2026-12-31 22:00:00', 'UTC'));
    allocateInMemory($counters, InvoiceType::Invoice, CarbonImmutable::parse('2026-12-31 22:30:00', 'UTC'));

    expect(allocateInMemory($counters, InvoiceType::Invoice, CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC')))
        ->toBe('2027/INVOICE/1');
});

it('numbers invoices and corrections separately', function () {
    $counters = [];
    $at = CarbonImmutable::parse('2026-05-01 08:00:00', 'UTC');

    allocateInMemory($counters, InvoiceType::Invoice, $at);
    allocateInMemory($counters, InvoiceType::Invoice, $at);

    expect(allocateInMemory($counters, InvoiceType::Correction, $at))->toBe('2026/CORRECTION/1')
        ->and(allocateInMemory($counters, InvoiceType::Invoice, $at))->toBe('2026/INVOICE/3');
});
