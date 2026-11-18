<?php

namespace App\Listeners\Refunds;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Jobs\Refunds\RefundOrder;
use App\Models\Order;
use App\Services\Orders\CancelOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundAllOrders implements ShouldQueue
{
    public function __construct(private CancelOrder $cancelOrder) {}

    public function viaQueue(): string
    {
        return QueueName::Refunds->value;
    }

    /**
     * Pending orders are cancelled here, because they have no money to return. Every paid order
     * gets its own job in one batch, so one failing refund does not stop the others. A second run
     * finds only the orders that are still paid, and each job skips an order refunded meanwhile.
     */
    public function handle(EventCancelled $event): void
    {
        $pendingIds = Order::query()->where('event_id', $event->eventId)->where('status', OrderStatus::Pending)->orderBy('id')->pluck('id');

        foreach ($pendingIds as $orderId) {
            $this->cancelOrder->forCancelledEvent($orderId);
        }

        $jobs = Order::query()
            ->where('event_id', $event->eventId)
            ->where('status', OrderStatus::Paid)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (int $orderId) => new RefundOrder($orderId));

        if ($jobs->isEmpty()) {
            return;
        }

        Bus::batch($jobs->all())
            ->name("Refunds of cancelled event #{$event->eventId}")
            ->onQueue(QueueName::Refunds->value)
            ->allowFailures()
            ->dispatch();
    }

    public function failed(EventCancelled $event, Throwable $exception): void
    {
        Log::error('Refunding the orders of a cancelled event failed', ['event_id' => $event->eventId, 'error' => $exception::class]);
    }
}
