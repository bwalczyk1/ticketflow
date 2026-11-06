<?php

namespace Tests\Integration\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Puts a step of another client at an exact place of the code under test, so an interleaving
 * of two connections does not depend on timing.
 */
class Interleave
{
    public const ORDER_LOCK = 'from `orders` where `orders`.`id` = ? limit 1 for update';

    /**
     * Any statement about one order, whether it is a locking read or a plain conditional update.
     */
    public const ORDER_ROW = '`orders`.`id` = ?';

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

    /**
     * Whether another client has to wait for this row, checked with a one second lock timeout.
     */
    public static function rowIsLocked(Connection $connection, string $table, int $id): bool
    {
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $connection->transaction(function () use ($connection, $table, $id) {
                $connection->table($table)->where('id', $id)->lockForUpdate()->first();
            });
        } catch (QueryException) {
            return true;
        }

        return false;
    }

    /**
     * The step of a request that started the payment first and committed.
     */
    public static function commitPayment(Connection $connection, Order $order): void
    {
        $connection->table('payments')->insert([
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
