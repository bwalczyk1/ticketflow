<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Models\Order;

class OrderTransition
{
    /**
     * Compare-and-set on the status column. Returns true only for the caller that won,
     * and only the winner may dispatch the follow-up event.
     */
    public function apply(Order $order, OrderStatus $to): bool
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new InvalidStatusTransition;
        }

        $updated = Order::query()
            ->whereKey($order->getKey())
            ->where('status', $order->status)
            ->update(['status' => $to]);

        if ($updated === 0) {
            return false;
        }

        $order->status = $to;
        $order->syncOriginalAttribute('status');

        return true;
    }
}
