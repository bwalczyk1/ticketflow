<?php

use App\Enums\InvoiceType;
use App\Models\InvoiceSequence;
use App\Services\Documents\InvoiceNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('allocates the next number under a row lock', function () {
    InvoiceSequence::factory()->create(['year' => 2026, 'type' => InvoiceType::Invoice, 'last_number' => 41]);
    $at = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $allocated = (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);

    expect($allocated)->toBe(['year' => 2026, 'number' => 42])
        ->and(DB::table('invoice_sequences')->where('year', 2026)->value('last_number'))->toBe(42)
        ->and(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'for update')))->toBeTrue();
});

it('creates the sequence row for a new year', function () {
    $at = CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC');

    $first = (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);
    $second = (new InvoiceNumberAllocator)->next(InvoiceType::Invoice, $at);
    $correction = (new InvoiceNumberAllocator)->next(InvoiceType::Correction, $at);

    expect($first)->toBe(['year' => 2027, 'number' => 1])
        ->and($second)->toBe(['year' => 2027, 'number' => 2])
        ->and($correction)->toBe(['year' => 2027, 'number' => 1])
        ->and(DB::table('invoice_sequences')->count())->toBe(2);
});

it('does not use up a number when the invoice already exists', function () {
    $at = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $allocator = new InvoiceNumberAllocator;

    // The caller allocates only after its own check, in the same transaction, so aborting returns the number.
    try {
        DB::transaction(function () use ($allocator, $at) {
            $allocator->next(InvoiceType::Invoice, $at);

            throw new RuntimeException('invoice already exists');
        });
    } catch (RuntimeException) {
        //
    }

    expect($allocator->next(InvoiceType::Invoice, $at))->toBe(['year' => 2026, 'number' => 1]);
});
