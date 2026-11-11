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
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateSalesSummary implements ShouldQueue
{
    public function __construct(private SalesSummary $salesSummary) {}

    public function viaQueue(): string
    {
        return QueueName::Stats->value;
    }

    public function handle(OrderPaid|OrderRefunded|TicketCheckedIn $event): void
    {
        $eventId = $event instanceof TicketCheckedIn
            ? $this->eventOfTicket($event->ticketId)
            : Order::query()->whereKey($event->orderId)->value('event_id');

        if ($eventId !== null) {
            $this->salesSummary->recalculate($eventId);
        }
    }

    public function failed(OrderPaid|OrderRefunded|TicketCheckedIn $event, Throwable $exception): void
    {
        Log::error('Updating the sales summary failed', [
            'event' => $event::class,
            'order_id' => $event->orderId ?? null,
            'ticket_id' => $event->ticketId ?? null,
            'error' => $exception::class,
        ]);
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
