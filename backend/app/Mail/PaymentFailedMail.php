<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Documents\Pln;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent right away by SendPaymentFailedEmail, which already runs on the mail queue, so it is not queued itself.
 */
class PaymentFailedMail extends Mailable
{
    /**
     * @param  Order  $order  with user and event loaded
     */
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Płatność nieudana: {$this->order->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-failed', with: [
            'buyer' => $this->order->user,
            'event' => $this->order->event,
            'orderId' => $this->order->id,
            'total' => Pln::format($this->order->total),
        ]);
    }
}
