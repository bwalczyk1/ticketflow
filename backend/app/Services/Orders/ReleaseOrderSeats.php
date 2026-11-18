<?php

namespace App\Services\Orders;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Events\Orders\SeatsReleased;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class ReleaseOrderSeats
{
    /**
     * Statuses that gave up their held seats. Paid and refunded orders keep them in sold, not in held.
     */
    private const RELEASING_STATUSES = [OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled];

    /**
     * Gives back the held seats of an order that ended without a payment.
     */
    public function handle(int $orderId): void
    {
        $this->release($orderId, self::RELEASING_STATUSES, 'held');
    }

    /**
     * Gives back the sold seats of a refunded order.
     */
    public function handleRefunded(int $orderId): void
    {
        $this->release($orderId, [OrderStatus::Refunded], 'sold');
    }

    /**
     * Compare-and-set on seats_released_at in one transaction with the pool decrement, so several
     * events about the same order, or a retried job, give the seats back only once. Seats of a
     * cancelled event are not offered to the waitlist, because nobody can buy them any more.
     *
     * @param  list<OrderStatus>  $statuses
     * @param  'held'|'sold'  $column
     */
    private function release(int $orderId, array $statuses, string $column): void
    {
        DB::transaction(function () use ($orderId, $statuses, $column) {
            $claimed = Order::query()
                ->whereKey($orderId)
                ->whereNull('seats_released_at')
                ->whereIn('status', $statuses)
                ->update(['seats_released_at' => now()]);

            if ($claimed === 0) {
                return;
            }

            $eventId = Order::query()->whereKey($orderId)->value('event_id');
            $offerToWaitlist = Event::query()->whereKey($eventId)->value('status') !== EventStatus::Cancelled;
            $items = OrderItem::query()->where('order_id', $orderId)->orderBy('ticket_type_id')->get(['id', 'ticket_type_id', 'quantity']);

            foreach ($items as $item) {
                TicketType::query()
                    ->whereKey($item->ticket_type_id)
                    ->decrement($column, $item->quantity, ['version' => DB::raw('version + 1')]);

                if ($offerToWaitlist) {
                    SeatsReleased::dispatch($item->ticket_type_id, $item->quantity);
                }
            }
        });
    }
}
