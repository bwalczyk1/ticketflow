<?php

namespace App\Listeners\Refunds;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\Order;
use App\Services\Notifications\NotificationLedger;
use App\Services\Refunds\StartRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAttendees implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    public function viaQueue(): string
    {
        return QueueName::Mail->value;
    }

    /**
     * It runs next to RefundAllOrders, so an order can be paid or already refunded because of the
     * cancellation; an attendee who refunded earlier is left out. The ledger key
     * event:{id}:cancelled:user:{id} lets one e-mail per attendee out, however often the job runs.
     */
    public function handle(EventCancelled $event): void
    {
        $cancelled = Event::query()->find($event->eventId, ['id', 'title', 'status', 'starts_at', 'timezone']);

        if ($cancelled?->status !== EventStatus::Cancelled) {
            return;
        }

        $orders = Order::query()
            ->with('user:id,name,email')
            ->where('event_id', $cancelled->id)
            ->where(fn (Builder $orders) => $orders
                ->where('status', OrderStatus::Paid)
                ->orWhere(fn (Builder $refunded) => $refunded
                    ->where('status', OrderStatus::Refunded)
                    ->whereHas('refund', fn (Builder $refunds) => $refunds->where('reason', StartRefund::EVENT_CANCELLED_REASON))))
            ->orderBy('id')
            ->get(['id', 'user_id', 'total']);

        foreach ($orders->groupBy('user_id') as $userId => $attendeeOrders) {
            $attendee = $attendeeOrders->first()->user;
            $refundTotal = $attendeeOrders->sum('total');

            $this->ledger->once("event:{$cancelled->id}:cancelled:user:{$userId}", function () use ($cancelled, $attendee, $refundTotal) {
                Mail::to($attendee)->send(new EventCancelledMail($cancelled, $attendee, $refundTotal));
            });
        }
    }

    public function failed(EventCancelled $event, Throwable $exception): void
    {
        Log::error('Notifying attendees of a cancelled event failed', ['event_id' => $event->eventId, 'error' => $exception::class]);
    }
}
