<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Documents\Pln;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by NotifyOrganizer, which already runs on the notifications queue.
 */
class OrganizerOrderMail extends Mailable
{
    /**
     * @param  Order  $order  with event.organizer and items loaded
     */
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nowe zamówienie: {$this->order->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.organizer-order', with: [
            'organizer' => $this->order->event->organizer,
            'event' => $this->order->event,
            'orderId' => $this->order->id,
            'ticketCount' => $this->order->items->sum('quantity'),
            'total' => Pln::format($this->order->total),
        ]);
    }
}
