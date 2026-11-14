<?php

namespace App\Jobs\Notifications;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One reminder for one attendee, queued by SendEventReminders a day before the event.
 */
class SendEventReminder implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly int $eventId, public readonly int $userId)
    {
        $this->onQueue(QueueName::Mail->value);
    }

    /**
     * The ledger key event:{id}:reminder:{userId} lets the e-mail out once. A cancelled event or
     * tickets refunded since the job was queued need no reminder.
     */
    public function handle(NotificationLedger $ledger): void
    {
        $event = Event::query()->find($this->eventId, ['id', 'title', 'venue', 'city', 'timezone', 'starts_at', 'status']);

        if ($event?->status !== EventStatus::Published) {
            return;
        }

        $ticketCount = (int) OrderItem::query()
            ->whereHas('order', fn (Builder $orders) => $orders
                ->where('event_id', $event->id)
                ->where('user_id', $this->userId)
                ->where('status', OrderStatus::Paid))
            ->sum('quantity');

        $user = User::query()->find($this->userId, ['id', 'name', 'email']);

        if ($ticketCount === 0 || $user === null) {
            return;
        }

        $ledger->once("event:{$event->id}:reminder:{$user->id}", function () use ($event, $user, $ticketCount) {
            Mail::to($user)->send(new EventReminderMail($event, $user, $ticketCount));
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Sending an event reminder failed', ['event_id' => $this->eventId, 'user_id' => $this->userId, 'error' => $exception::class]);
    }
}
