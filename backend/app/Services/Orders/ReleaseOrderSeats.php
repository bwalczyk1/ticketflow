<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\Orders\SeatsReleased;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class ReleaseOrderSeats
{
    /**
     * Statuses that gave up their seats. Paid and refunded orders keep them in sold, not in held.
     */
    private const RELEASING_STATUSES = [OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled];

    /**
     * Compare-and-set on seats_released_at in one transaction with the held decrement, so several
     * events about the same order, or a retried job, give the seats back only once.
     */
    public function handle(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $claimed = Order::query()
                ->whereKey($orderId)
                ->whereNull('seats_released_at')
                ->whereIn('status', self::RELEASING_STATUSES)
                ->update(['seats_released_at' => now()]);

            if ($claimed === 0) {
                return;
            }

            $items = OrderItem::query()->where('order_id', $orderId)->orderBy('ticket_type_id')->get(['id', 'ticket_type_id', 'quantity']);

            foreach ($items as $item) {
                TicketType::query()
                    ->whereKey($item->ticket_type_id)
                    ->decrement('held', $item->quantity, ['version' => DB::raw('version + 1')]);

                SeatsReleased::dispatch($item->ticket_type_id, $item->quantity);
            }
        });
    }
}
