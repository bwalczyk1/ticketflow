<?php

namespace App\Services\Documents;

use App\Enums\InvoiceType;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberAllocator
{
    public function __construct(private ?ConnectionInterface $connection = null) {}

    /**
     * Run it in the same transaction that saves the invoice, so a rollback gives the number back.
     *
     * @return array{year: int, number: int}
     */
    public function next(InvoiceType $type, CarbonInterface $at): array
    {
        $connection = $this->connection ?? DB::connection();
        $year = InvoiceNumbering::yearOf($at, config('app.business_timezone'));

        return $connection->transaction(function () use ($connection, $type, $year) {
            // Creating the row first means there is always a row to lock, even for a new year.
            $connection->table('invoice_sequences')->insertOrIgnore([
                'year' => $year,
                'type' => $type->value,
                'last_number' => 0,
            ]);

            $last = $connection->table('invoice_sequences')
                ->where('year', $year)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->value('last_number');

            $number = InvoiceNumbering::next($last);

            $connection->table('invoice_sequences')
                ->where('year', $year)
                ->where('type', $type->value)
                ->update(['last_number' => $number]);

            return ['year' => $year, 'number' => $number];
        });
    }
}
