<?php

namespace App\Listeners\Waitlist;

use App\Enums\QueueName;
use App\Events\Orders\SeatsReleased;
use App\Services\Waitlist\WaitlistOffers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class OfferSeatsToWaitlist implements ShouldQueue
{
    public function __construct(private WaitlistOffers $waitlistOffers) {}

    public function viaQueue(): string
    {
        return QueueName::Waitlist->value;
    }

    /**
     * Seats released by an order are offered already in the releasing transaction. This listener
     * covers every other way seats come free, and running it again is harmless.
     */
    public function handle(SeatsReleased $event): void
    {
        $this->waitlistOffers->offerFreeSeats($event->ticketTypeId);
    }

    public function failed(SeatsReleased $event, Throwable $exception): void
    {
        Log::error('Offering seats to the waitlist failed', ['ticket_type_id' => $event->ticketTypeId, 'error' => $exception::class]);
    }
}
