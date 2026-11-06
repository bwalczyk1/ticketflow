<?php

namespace Tests\Integration\Orders;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Puts a step of another client at an exact place of the code under test, so an interleaving
 * of two connections does not depend on timing.
 */
class Interleave
{
    /**
     * Runs the step right before a query on the default connection that contains the given SQL,
     * at most the given number of times.
     */
    public static function before(string $sql, Closure $step, int $times = 1): void
    {
        DB::connection()->beforeExecuting(function (string $query) use ($sql, $step, &$times) {
            if ($times > 0 && str_contains($query, $sql)) {
                $times--;

                $step();
            }
        });
    }
}
