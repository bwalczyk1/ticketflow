<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendEventReminder, which already runs on the mail queue, so it is not queued itself.
 */
class EventReminderMail extends Mailable
{
    public function __construct(public Event $event, public User $attendee, public int $ticketCount) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Przypomnienie: {$this->event->title} już jutro");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-reminder', with: [
            'attendee' => $this->attendee,
            'event' => $this->event,
            'startsAt' => $this->event->localStartsAt(),
            'ticketCount' => $this->ticketCount,
            'ticketsUrl' => rtrim(config('app.url'), '/').'/tickets',
        ]);
    }
}
