<?php

namespace App\Services\Tickets;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class VoidOrderTickets
{
    /**
     * One conditional update: a repeated run finds no valid tickets left, and a ticket that was
     * already used at the entrance keeps its history. The order row lock is the same one
     * IssueTickets takes, so tickets issued in parallel are either already there or never created.
     */
    public function handle(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first(['id', 'status']);

            if ($order?->status !== OrderStatus::Refunded) {
                return;
            }

            Ticket::query()
                ->whereIn('order_item_id', OrderItem::query()->select('id')->where('order_id', $orderId))
                ->where('status', TicketStatus::Valid)
                ->update(['status' => TicketStatus::Void]);
        });
    }
}
