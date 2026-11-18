<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendWaitlistOfferEmail, which already runs on the mail queue, so it is not queued itself.
 */
class WaitlistOfferMail extends Mailable
{
    /**
     * @param  WaitlistEntry  $entry  with user and ticketType.event loaded
     */
    public function __construct(public WaitlistEntry $entry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Zwolniły się miejsca: {$this->entry->ticketType->event->title}");
    }

    public function content(): Content
    {
        $event = $this->entry->ticketType->event;

        return new Content(view: 'mail.waitlist-offer', with: [
            'buyer' => $this->entry->user,
            'event' => $event,
            'ticketTypeName' => $this->entry->ticketType->name,
            'quantity' => $this->entry->quantity,
            'startsAt' => $event->localStartsAt(),
            'offeredUntil' => $this->entry->offered_until->setTimezone($event->timezone),
            'waitlistUrl' => rtrim(config('app.url'), '/').'/waitlist',
        ]);
    }
}
