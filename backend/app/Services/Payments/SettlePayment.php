<?php

namespace App\Services\Payments;

use App\Contracts\PaymentResultHandler;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Payments\PaymentFailed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Orders\OrderTransition;
use Illuminate\Support\Facades\DB;

class SettlePayment implements PaymentResultHandler
{
    public function __construct(
        private OrderTransition $orderTransition,
        private RefundLatePayment $refundLatePayment,
    ) {}

    public function succeeded(Payment $payment, ?string $providerRef = null): void
    {
        DB::transaction(function () use ($payment, $providerRef) {
            $order = $this->lockOrder($payment);

            if (! $this->settle($payment, PaymentStatus::Succeeded, $providerRef)) {
                return;
            }

            if ($order->status !== OrderStatus::Pending) {
                $this->refundLatePayment->handle($payment, $order);

                return;
            }

            $this->orderTransition->apply($order, OrderStatus::Paid);
            $this->moveSeatsToSold($order);

            OrderPaid::dispatch($order->id);
        });
    }

    public function failed(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $order = $this->lockOrder($payment);

            if (! $this->settle($payment, PaymentStatus::Failed)) {
                return;
            }

            if ($order->status !== OrderStatus::Pending) {
                return;
            }

            $this->orderTransition->apply($order, OrderStatus::Failed);

            PaymentFailed::dispatch($order->id, $reason);
        });
    }

    /**
     * The same lock as paying, cancelling and expiring, so the order cannot change status while
     * the result is being applied.
     */
    private function lockOrder(Payment $payment): Order
    {
        return Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Compare-and-set on the payment status: only the first result counts, the rest are repeats.
     */
    private function settle(Payment $payment, PaymentStatus $status, ?string $providerRef = null): bool
    {
        $updated = Payment::query()
            ->whereKey($payment->id)
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => $status, 'provider_ref' => $providerRef]);

        return $updated === 1;
    }

    private function moveSeatsToSold(Order $order): void
    {
        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('ticket_type_id')->get();

        foreach ($items as $item) {
            TicketType::query()->whereKey($item->ticket_type_id)->update([
                'held' => DB::raw("held - {$item->quantity}"),
                'sold' => DB::raw("sold + {$item->quantity}"),
                'version' => DB::raw('version + 1'),
            ]);
        }
    }
}
