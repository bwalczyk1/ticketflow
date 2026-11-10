<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendTicketsEmail, which already runs on the mail queue, so it is not queued itself.
 */
class TicketsMail extends Mailable
{
    /**
     * @param  Order  $order  with user and event loaded
     * @param  Collection<int, Ticket>  $tickets  with pdf_path
     */
    public function __construct(public Order $order, public Collection $tickets) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Twoje bilety: {$this->order->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tickets', with: [
            'buyer' => $this->order->user,
            'event' => $this->order->event,
            'startsAt' => $this->order->event->localStartsAt(),
            'ticketCount' => $this->tickets->count(),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->tickets
            ->map(fn (Ticket $ticket) => Attachment::fromStorageDisk('local', $ticket->pdf_path)
                ->as("bilet-{$ticket->code}.pdf")
                ->withMime('application/pdf'))
            ->values()
            ->all();
    }
}
