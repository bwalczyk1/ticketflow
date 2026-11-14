<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Exceptions\Domain\PaymentAlreadyStarted;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(
        private OrderTransition $orderTransition,
        private ReleaseOrderSeats $releaseOrderSeats,
    ) {}

    /**
     * The row lock serialises cancelling with paying, so a payment cannot start on a cancelled order.
     *
     * @throws InvalidStatusTransition
     * @throws PaymentAlreadyStarted
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail(['id', 'status']);

            if ($locked->status->holdsSeats() && $locked->payment()->exists()) {
                throw new PaymentAlreadyStarted;
            }

            $this->orderTransition->apply($locked, OrderStatus::Cancelled);
            $this->releaseOrderSeats->handle($locked->id);
        });
    }

    /**
     * An order of a cancelled event is cancelled even while its payment runs: a payment that
     * succeeds later finds a final order and is refunded by RefundLatePayment.
     */
    public function forCancelledEvent(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $locked = Order::query()->whereKey($orderId)->lockForUpdate()->first(['id', 'status']);

            if ($locked?->status !== OrderStatus::Pending) {
                return;
            }

            $this->orderTransition->apply($locked, OrderStatus::Cancelled);
            $this->releaseOrderSeats->handle($locked->id);
        });
    }
}
