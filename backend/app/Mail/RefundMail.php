<?php

namespace App\Mail;

use App\Models\Refund;
use App\Services\Documents\Pln;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendRefundEmail, which already runs on the mail queue, so it is not queued itself.
 */
class RefundMail extends Mailable
{
    /**
     * @param  Refund  $refund  with order, order.user and order.event loaded
     */
    public function __construct(public Refund $refund) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Zwrot za zamówienie: {$this->refund->order->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.refund', with: [
            'buyer' => $this->refund->order->user,
            'event' => $this->refund->order->event,
            'orderId' => $this->refund->order_id,
            'amount' => Pln::format($this->refund->amount),
            'reason' => $this->refund->reason,
        ]);
    }
}
