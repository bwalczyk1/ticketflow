<?php

namespace App\Listeners\Refunds;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Mail\RefundMail;
use App\Models\Refund;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendRefundEmail implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    public function viaQueue(): string
    {
        return QueueName::Mail->value;
    }

    /**
     * The ledger key order:{id}:refunded lets the e-mail out once, however often the job runs.
     */
    public function handle(OrderRefunded $event): void
    {
        $refund = Refund::query()
            ->with(['order:id,user_id,event_id,status', 'order.user:id,name,email', 'order.event:id,title'])
            ->find($event->refundId, ['id', 'order_id', 'amount', 'reason']);

        if ($refund?->order->status !== OrderStatus::Refunded) {
            return;
        }

        $this->ledger->once("order:{$refund->order_id}:refunded", function () use ($refund) {
            Mail::to($refund->order->user)->send(new RefundMail($refund));
        });
    }

    public function failed(OrderRefunded $event, Throwable $exception): void
    {
        Log::error('Sending the refund e-mail failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }
}
