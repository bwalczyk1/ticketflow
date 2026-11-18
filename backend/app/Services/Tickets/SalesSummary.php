<?php

namespace App\Services\Tickets;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * EventSalesSummary is a read model: every update counts it again from orders, refunds and
 * tickets, so the result does not depend on how many times or in which order events arrive.
 */
class SalesSummary
{
    private const COLUMNS = ['event_id', 'tickets_sold', 'revenue', 'refunds', 'checked_in', 'updated_at'];

    public function recalculate(int $eventId): void
    {
        DB::transaction(function () use ($eventId) {
            // Two recalculations of one event run one after another, so an older count is never written last.
            if (Event::query()->whereKey($eventId)->lockForUpdate()->first(['id']) === null) {
                return;
            }

            EventSalesSummary::query()->upsert([[
                'event_id' => $eventId,
                'tickets_sold' => (int) OrderItem::query()->whereHas('order', $this->paidOrdersOf($eventId))->sum('quantity'),
                'revenue' => (int) Order::query()->where($this->paidOrdersOf($eventId))->sum('total'),
                // Only refunds of paid orders, which are REFUNDED now; a returned late payment was never revenue.
                'refunds' => (int) Refund::query()
                    ->whereHas('order', fn (Builder $orders) => $orders->where('event_id', $eventId)->where('status', OrderStatus::Refunded))
                    ->where('status', '!=', RefundStatus::Failed)
                    ->sum('amount'),
                'checked_in' => Ticket::query()
                    ->whereHas('orderItem.order', fn (Builder $orders) => $orders->where('event_id', $eventId))
                    ->where('status', TicketStatus::Used)
                    ->count(),
            ]], ['event_id'], ['tickets_sold', 'revenue', 'refunds', 'checked_in']);
        });
    }

    /**
     * An event without sales yet has no row, so it gets an unsaved one with zeros.
     */
    public function forEvent(Event $event): EventSalesSummary
    {
        return EventSalesSummary::query()->find($event->id, self::COLUMNS)
            ?? new EventSalesSummary(['event_id' => $event->id, 'tickets_sold' => 0, 'revenue' => 0, 'refunds' => 0, 'checked_in' => 0]);
    }

    /**
     * @return callable(Builder<Order>): void
     */
    private function paidOrdersOf(int $eventId): callable
    {
        return function (Builder $orders) use ($eventId) {
            $orders->where('event_id', $eventId)->where('status', OrderStatus::Paid);
        };
    }
}
