<?php

namespace App\Services\Tickets;

use App\Enums\OrderStatus;
use App\Events\Orders\OrderPaid;
use App\Models\Order;

/**
 * The commit of a payment and the push of OrderPaid to Redis are two writes, so a crash between
 * them loses the event. Every OrderPaid consumer is idempotent, so sending it again is safe.
 */
class ReconcileOrders
{
    /**
     * Long enough for a normal fan-out to have issued the tickets.
     */
    private const GRACE_MINUTES = 5;

    public function handle(): int
    {
        // updated_at is the time of the PAID transition, which is the last change of a paid order.
        $orderIds = Order::query()
            ->where('status', OrderStatus::Paid)
            ->where('updated_at', '<', now()->subMinutes(self::GRACE_MINUTES))
            ->whereDoesntHave('tickets')
            ->orderBy('id')
            ->pluck('id');

        foreach ($orderIds as $orderId) {
            OrderPaid::dispatch($orderId);
        }

        return $orderIds->count();
    }
}
