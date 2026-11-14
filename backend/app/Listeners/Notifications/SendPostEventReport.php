<?php

namespace App\Listeners\Notifications;

use App\Enums\EventStatus;
use App\Enums\QueueName;
use App\Events\Catalog\EventFinished;
use App\Mail\PostEventReportMail;
use App\Models\Event;
use App\Services\Notifications\NotificationLedger;
use App\Services\Tickets\SalesSummary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPostEventReport implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger, private SalesSummary $salesSummary) {}

    public function viaQueue(): string
    {
        return QueueName::Notifications->value;
    }

    /**
     * The summary is counted again from the source first, because the listeners that keep it
     * current may still be behind. The ledger key event:{id}:report lets the e-mail out once.
     */
    public function handle(EventFinished $event): void
    {
        $finished = Event::query()
            ->with('organizer:id,name,email')
            ->find($event->eventId, ['id', 'organizer_id', 'title', 'timezone', 'starts_at', 'status']);

        if ($finished?->status !== EventStatus::Finished) {
            return;
        }

        $this->salesSummary->recalculate($finished->id);

        $summary = $this->salesSummary->forEvent($finished);

        $this->ledger->once("event:{$finished->id}:report", function () use ($finished, $summary) {
            Mail::to($finished->organizer)->send(new PostEventReportMail($finished, $summary));
        });
    }

    public function failed(EventFinished $event, Throwable $exception): void
    {
        Log::error('Sending the post-event report failed', ['event_id' => $event->eventId, 'error' => $exception::class]);
    }
}
