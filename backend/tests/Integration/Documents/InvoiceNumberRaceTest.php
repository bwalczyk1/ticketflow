<?php

use App\Enums\InvoiceType;
use App\Services\Documents\InvoiceNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('makes a second connection wait for the number lock instead of repeating a number', function () {
    $at = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $other = secondConnection();
    $other->statement('SET SESSION innodb_lock_wait_timeout = 1');

    (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);

    DB::transaction(function () use ($at, $other) {
        $held = (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);

        $blocked = false;

        try {
            $other->transaction(function () use ($other) {
                $other->table('invoice_sequences')
                    ->where('year', 2026)
                    ->where('type', InvoiceType::Invoice->value)
                    ->lockForUpdate()
                    ->first();
            });
        } catch (QueryException) {
            $blocked = true;
        }

        expect($held['number'])->toBe(2)
            ->and($blocked)->toBeTrue();
    });

    expect((new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at)['number'])->toBe(3);
});

it('hands out distinct numbers to two connections one after another', function () {
    $at = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $other = secondConnection();

    $fromOther = (new InvoiceNumberAllocator($other))->next(InvoiceType::Invoice, $at);
    $fromDefault = (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);

    expect([$fromOther['number'], $fromDefault['number']])->toBe([1, 2]);
});
