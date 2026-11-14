<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Exceptions\Domain\SalesClosed;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Exceptions\Domain\WaitlistOfferExpired;
use App\Exceptions\Domain\WaitlistOfferNotActive;
use App\Models\Order;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Orders\TicketAvailability;
use App\Services\Orders\TicketLimit;
use Illuminate\Support\Facades\DB;

class AcceptWaitlistOffer
{
    public function __construct(private TicketLimit $ticketLimit) {}

    /**
     * Turns an open offer into a pending order with the normal seat hold. The offered seats are
     * already in held, so they pass from the offer to the order without the pool changing and
     * are never free in between.
     *
     * @throws WaitlistOfferExpired
     * @throws WaitlistOfferNotActive
     * @throws SalesClosed
     * @throws TicketLimitExceeded
     */
    public function handle(User $user, int $entryId): Order
    {
        return DB::transaction(function () use ($user, $entryId) {
            // The same first statement as a reservation, so the tickets counted below cannot change meanwhile.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail(['id']);

            // The lock waits for the scheduler or a leave request that is closing this offer right now.
            $entry = WaitlistEntry::query()
                ->with(['ticketType:id,event_id,price', 'ticketType.event:id,status,starts_at'])
                ->lockForUpdate()
                ->findOrFail($entryId, ['id', 'user_id', 'ticket_type_id', 'quantity', 'status', 'offered_until']);

            $this->ensureOpen($entry);

            $event = $entry->ticketType->event;

            TicketAvailability::ensureOnSale($event->status, $event->starts_at, now());

            TicketAvailability::ensureWithinLimit(
                $entry->quantity,
                $this->ticketLimit->used($user->id, $event->id),
                config('ticketflow.ticket_limit_per_person'),
            );

            $order = $user->orders()->create([
                'event_id' => $event->id,
                'expires_at' => TicketAvailability::holdEndsAt(now(), config('ticketflow.hold_minutes')),
                'total' => $entry->quantity * $entry->ticketType->price,
            ]);

            $order->items()->create([
                'ticket_type_id' => $entry->ticket_type_id,
                'quantity' => $entry->quantity,
                'unit_price' => $entry->ticketType->price,
            ]);

            WaitlistEntry::query()->whereKey($entry->id)->update([
                'status' => WaitlistStatus::Converted,
                'order_id' => $order->id,
            ]);

            return $order;
        });
    }

    /**
     * @throws WaitlistOfferExpired
     * @throws WaitlistOfferNotActive
     */
    private function ensureOpen(WaitlistEntry $entry): void
    {
        if ($entry->status === WaitlistStatus::Expired) {
            throw new WaitlistOfferExpired;
        }

        if ($entry->status !== WaitlistStatus::Offered) {
            throw new WaitlistOfferNotActive;
        }

        if (WaitlistQueue::offerEnded($entry->offered_until, now())) {
            throw new WaitlistOfferExpired;
        }
    }
}
