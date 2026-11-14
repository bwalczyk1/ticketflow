<?php

namespace App\Services\Tickets;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;

class VoidOrderTickets
{
    /**
     * One conditional update: a repeated run finds no valid tickets left, and a ticket that was
     * already used at the entrance keeps its history.
     */
    public function handle(int $orderId): void
    {
        if (Order::query()->whereKey($orderId)->value('status') !== OrderStatus::Refunded) {
            return;
        }

        Ticket::query()
            ->whereIn('order_item_id', OrderItem::query()->select('id')->where('order_id', $orderId))
            ->where('status', TicketStatus::Valid)
            ->update(['status' => TicketStatus::Void]);
    }
}
