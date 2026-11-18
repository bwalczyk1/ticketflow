<?php

namespace App\Listeners\Waitlist;

use App\Enums\QueueName;
use App\Enums\WaitlistStatus;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Mail\WaitlistOfferMail;
use App\Models\WaitlistEntry;
use App\Services\Notifications\NotificationLedger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Mail;

#[Queue(QueueName::Mail)]
class SendWaitlistOfferEmail implements ShouldQueue
{
    public function __construct(private NotificationLedger $ledger) {}

    /**
     * An entry is offered seats at most once, so the ledger key waitlist:{id}:offer lets the
     * e-mail out once. An offer accepted, left or expired before the job ran needs no e-mail.
     */
    public function handle(WaitlistOfferCreated $event): void
    {
        $entry = WaitlistEntry::query()
            ->with([
                'user:id,name,email',
                'ticketType:id,event_id,name',
                'ticketType.event:id,title,venue,city,timezone,starts_at',
            ])
            ->find($event->waitlistEntryId, ['id', 'user_id', 'ticket_type_id', 'quantity', 'status', 'offered_until']);

        if ($entry?->status !== WaitlistStatus::Offered) {
            return;
        }

        $this->ledger->once("waitlist:{$entry->id}:offer", function () use ($entry) {
            Mail::to($entry->user)->send(new WaitlistOfferMail($entry));
        });
    }
}
