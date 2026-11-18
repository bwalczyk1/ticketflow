<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Services\Documents\Pln;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendPostEventReport, which already runs on a queue, so it is not queued itself.
 */
class PostEventReportMail extends Mailable
{
    /**
     * @param  Event  $event  with organizer loaded
     */
    public function __construct(public Event $event, public EventSalesSummary $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Podsumowanie wydarzenia: {$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.post-event-report', with: [
            'organizer' => $this->event->organizer,
            'event' => $this->event,
            'startsAt' => $this->event->localStartsAt(),
            'ticketsSold' => $this->summary->tickets_sold,
            'revenue' => Pln::format($this->summary->revenue),
            'refunds' => Pln::format($this->summary->refunds),
            'checkedIn' => $this->summary->checked_in,
        ]);
    }
}
