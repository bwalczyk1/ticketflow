<?php

namespace App\Listeners\Tickets;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Jobs\Documents\GenerateTicketPdfs;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IssueTickets implements ShouldQueue
{
    public function viaQueue(): string
    {
        return QueueName::Tickets->value;
    }

    /**
     * UNIQUE (order_item_id, position) makes the insert idempotent: a retry adds only the missing
     * tickets and keeps the codes already issued. The PDF job is dispatched on every run, so a crash
     * between the insert and the dispatch is repaired by the retry.
     */
    public function handle(OrderPaid $event): void
    {
        if (! $this->insertTickets($event->orderId)) {
            return;
        }

        GenerateTicketPdfs::dispatch($event->orderId);
    }

    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Issuing tickets failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }

    /**
     * The order row lock serialises issuing with refunding and voiding: a refund either waits for
     * the tickets and voids them, or commits first and this check sees REFUNDED.
     */
    private function insertTickets(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first(['id', 'status']);

            if ($order?->status !== OrderStatus::Paid) {
                return false;
            }

            $items = OrderItem::query()->where('order_id', $orderId)->orderBy('id')->get(['id', 'quantity']);
            $rows = [];

            foreach ($items as $item) {
                for ($position = 1; $position <= $item->quantity; $position++) {
                    $rows[] = [
                        'order_item_id' => $item->id,
                        'position' => $position,
                        'code' => (string) Str::uuid(),
                        'status' => TicketStatus::Valid->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            Ticket::query()->insertOrIgnore($rows);

            return true;
        });
    }
}
