<?php

namespace App\Listeners\Notifications;

use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Mail\OrganizerOrderMail;
use App\Models\Order;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyOrganizer implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    public function viaQueue(): string
    {
        return QueueName::Notifications->value;
    }

    /**
     * The ledger key order:{id}:organizer lets the e-mail out once, however often the job runs.
     */
    public function handle(OrderPaid $event): void
    {
        $order = Order::query()
            ->with(['event:id,organizer_id,title', 'event.organizer:id,name,email', 'items:id,order_id,quantity'])
            ->find($event->orderId, ['id', 'event_id', 'total']);

        if ($order === null) {
            return;
        }

        $this->ledger->once("order:{$order->id}:organizer", function () use ($order) {
            Mail::to($order->event->organizer)->send(new OrganizerOrderMail($order));
        });
    }

    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Notifying the organizer failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }
}
