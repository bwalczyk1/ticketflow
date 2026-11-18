<?php

namespace App\Listeners\Tickets;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Tickets\TicketsGenerated;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendTicketsEmail implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    public function viaQueue(): string
    {
        return QueueName::Mail->value;
    }

    /**
     * The ledger key order:{id}:tickets lets the e-mail out once. A missing PDF throws, so the
     * queue retries instead of sending tickets without their files.
     */
    public function handle(TicketsGenerated $event): void
    {
        $order = Order::query()
            ->with(['user:id,name,email', 'event:id,title,venue,city,timezone,starts_at'])
            ->find($event->orderId, ['id', 'user_id', 'event_id', 'status']);

        if ($order?->status !== OrderStatus::Paid) {
            return;
        }

        $tickets = Ticket::query()
            ->whereIn('order_item_id', OrderItem::query()->select('id')->where('order_id', $order->id))
            ->orderBy('id')
            ->get(['id', 'order_item_id', 'code', 'pdf_path']);

        if ($tickets->isEmpty() || $tickets->contains('pdf_path', null)) {
            throw new RuntimeException("Ticket PDFs of order {$order->id} are not ready");
        }

        $this->ledger->once("order:{$order->id}:tickets", function () use ($order, $tickets) {
            Mail::to($order->user)->send(new TicketsMail($order, $tickets));
        });
    }

    public function failed(TicketsGenerated $event, Throwable $exception): void
    {
        Log::error('Sending tickets by e-mail failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }
}
