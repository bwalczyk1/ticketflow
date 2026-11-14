<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Events\Orders\OrderPlaced;
use App\Exceptions\Domain\OrderNotPayable;
use App\Exceptions\Domain\PaymentAlreadyStarted;
use App\Exceptions\Domain\ReservationExpired;
use App\Exceptions\Domain\SalesClosed;
use App\Models\Order;
use App\Services\Orders\TicketAvailability;
use Illuminate\Support\Facades\DB;

class StartPayment
{
    /**
     * The locking read must stay the first statement of the transaction. It returns the newest
     * committed row, and the plain reads after it then get a snapshot taken once the lock is held,
     * so neither a parallel pay nor the scheduler can be missed.
     *
     * @throws ReservationExpired
     * @throws PaymentAlreadyStarted
     * @throws OrderNotPayable
     * @throws SalesClosed
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail(['id', 'event_id', 'status', 'total', 'expires_at']);

            $this->ensurePayable($locked);

            $locked->payment()->create(['amount' => $locked->total]);

            OrderPlaced::dispatch($locked->id);
        });
    }

    /**
     * Only an order that may still become PAID can be paid. An expired one answers 410, like a
     * hold that ended before the scheduler noticed it.
     */
    private function ensurePayable(Order $order): void
    {
        if ($order->status === OrderStatus::Expired) {
            throw new ReservationExpired;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            throw new OrderNotPayable;
        }

        if ($order->payment()->exists()) {
            throw new PaymentAlreadyStarted;
        }

        if (TicketAvailability::holdEnded($order->expires_at, now())) {
            throw new ReservationExpired;
        }

        $event = $order->event()->firstOrFail(['id', 'status', 'starts_at']);

        TicketAvailability::ensureOnSale($event->status, $event->starts_at, now());
    }
}
