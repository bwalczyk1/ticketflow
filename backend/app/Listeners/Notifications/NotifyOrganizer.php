<?php

namespace App\Listeners\Notifications;

use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Mail\OrganizerOrderMail;
use App\Models\Order;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Mail;

#[Queue(QueueName::Notifications)]
class NotifyOrganizer implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

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
}
