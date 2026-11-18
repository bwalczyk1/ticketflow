<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\ReservationExpired;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExpireReservations
{
    /**
     * A gateway gets this long to answer after the hold ended, before the seats go back.
     */
    private const PAYMENT_GRACE_MINUTES = 5;

    public function __construct(private OrderTransition $orderTransition) {}

    /**
     * @return int the number of expired orders
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();

        return Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $orderId) => $this->expire($orderId, $now))
            ->count();
    }

    /**
     * Everything is decided again under the row lock, because a payment may have started after
     * the list was read. The lock must stay the first statement, so that the payment read below
     * gets a snapshot taken after the lock and sees a pay request that committed just before it.
     */
    private function expire(int $orderId, CarbonImmutable $now): bool
    {
        return DB::transaction(function () use ($orderId, $now) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail(['id', 'status']);

            if ($order->status !== OrderStatus::Pending || $this->paymentInProgress($order, $now)) {
                return false;
            }

            $this->orderTransition->apply($order, OrderStatus::Expired);

            ReservationExpired::dispatch($order->id);

            return true;
        });
    }

    private function paymentInProgress(Order $order, CarbonImmutable $now): bool
    {
        $payment = $order->payment()->first(['id', 'status', 'created_at']);

        return $payment?->status === PaymentStatus::Pending
            && $payment->created_at > $now->subMinutes(self::PAYMENT_GRACE_MINUTES);
    }
}
