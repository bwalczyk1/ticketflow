<?php

namespace App\Listeners\Stats;

use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Events\Refunds\OrderRefunded;
use App\Events\Tickets\TicketCheckedIn;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\Tickets\SalesSummary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;

#[Queue(QueueName::Stats)]
class UpdateSalesSummary implements ShouldQueue
{
    public function __construct(private SalesSummary $salesSummary) {}

    public function handle(OrderPaid|OrderRefunded|TicketCheckedIn $event): void
    {
        $eventId = $event instanceof TicketCheckedIn
            ? $this->eventOfTicket($event->ticketId)
            : Order::query()->whereKey($event->orderId)->value('event_id');

        if ($eventId !== null) {
            $this->salesSummary->recalculate($eventId);
        }
    }

    private function eventOfTicket(int $ticketId): ?int
    {
        return Ticket::query()
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('tickets.id', $ticketId)
            ->value('orders.event_id');
    }
}
