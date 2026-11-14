<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use App\Services\Documents\Pln;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by NotifyAttendees, which already runs on the mail queue, so it is not queued itself.
 */
class EventCancelledMail extends Mailable
{
    /**
     * @param  int  $refundTotal  the paid orders of the attendee in grosz
     */
    public function __construct(public Event $event, public User $attendee, public int $refundTotal) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Wydarzenie odwołane: {$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-cancelled', with: [
            'attendee' => $this->attendee,
            'event' => $this->event,
            'startsAt' => $this->event->localStartsAt(),
            'amount' => Pln::format($this->refundTotal),
        ]);
    }
}
