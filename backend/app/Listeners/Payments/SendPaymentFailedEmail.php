<?php

namespace App\Listeners\Payments;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Payments\PaymentFailed;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Mail;

#[Queue(QueueName::Mail)]
class SendPaymentFailedEmail implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    /**
     * The ledger key order:{id}:payment-failed lets the e-mail out once, however often the job runs.
     */
    public function handle(PaymentFailed $event): void
    {
        $order = Order::query()
            ->with(['user:id,name,email', 'event:id,title'])
            ->find($event->orderId, ['id', 'user_id', 'event_id', 'status', 'total']);

        if ($order?->status !== OrderStatus::Failed) {
            return;
        }

        $this->ledger->once("order:{$order->id}:payment-failed", function () use ($order) {
            Mail::to($order->user)->send(new PaymentFailedMail($order));
        });
    }
}
