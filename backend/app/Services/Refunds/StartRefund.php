<?php

namespace App\Services\Refunds;

use App\Enums\OrderStatus;
use App\Events\Refunds\OrderRefunded;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Exceptions\Domain\RefundWindowClosed;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Orders\OrderTransition;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid order into a refunded one with a PENDING refund of the full amount. The money,
 * tickets, correction invoice, seats and e-mail follow in the listeners of OrderRefunded.
 */
class StartRefund
{
    public const EVENT_CANCELLED_REASON = 'Wydarzenie odwołane';

    public function __construct(private OrderTransition $orderTransition) {}

    /**
     * @throws OrderNotRefundable
     * @throws RefundWindowClosed
     */
    public function forAttendee(Order $order, ?string $reason): Refund
    {
        return $this->refund($order->id, $reason, checkDeadline: true);
    }

    /**
     * A cancelled event gives the money back whenever it is cancelled, so the 48 hour rule does not apply.
     *
     * @throws OrderNotRefundable
     */
    public function forCancelledEvent(int $orderId): Refund
    {
        return $this->refund($orderId, self::EVENT_CANCELLED_REASON, checkDeadline: false);
    }

    /**
     * The locking read stays first, so a parallel refund waits and then sees REFUNDED. The status
     * compare-and-set and UNIQUE (order_id) on refunds stay as the last defence.
     *
     * @throws OrderNotRefundable
     * @throws RefundWindowClosed
     */
    private function refund(int $orderId, ?string $reason, bool $checkDeadline): Refund
    {
        return DB::transaction(function () use ($orderId, $reason, $checkDeadline) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail(['id', 'event_id', 'status', 'total']);

            if ($checkDeadline) {
                $event = $order->event()->firstOrFail(['id', 'starts_at']);

                RefundEligibility::ensureRefundable($order->status, $event->starts_at, now(), config('ticketflow.refund_cutoff_hours'));
            }

            if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
                throw new OrderNotRefundable;
            }

            $this->orderTransition->apply($order, OrderStatus::Refunded);

            $refund = $order->refund()->create(['amount' => $order->total, 'reason' => $reason]);

            OrderRefunded::dispatch($order->id, $refund->id);

            return $refund;
        });
    }
}
