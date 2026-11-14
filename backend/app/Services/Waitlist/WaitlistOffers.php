<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Exceptions\Domain\ReservationConflict;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use App\Services\Orders\TicketAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

class WaitlistOffers
{
    /**
     * Offers the free seats of a pool to the people waiting for it. The result depends only on
     * the current state, not on how many seats an event says were released, so a retried job or
     * a second caller finds the seats already held and offers nothing twice.
     *
     * Called inside the transaction that frees the seats, it holds them again before that
     * transaction commits, so a normal purchase never sees them free.
     *
     * @return list<int> ids of the entries that got an offer
     *
     * @throws ReservationConflict
     */
    public function offerFreeSeats(int $ticketTypeId): array
    {
        $attempts = config('ticketflow.reservation_attempts');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(fn () => $this->offer($ticketTypeId));
            } catch (ReservationConflict) {
                // A purchase changed the pool first, so the next attempt reads it again.
            }

            if ($attempt < $attempts) {
                Sleep::for(random_int(10, 50))->milliseconds();
            }
        }

        throw new ReservationConflict;
    }

    /**
     * @return list<int>
     *
     * @throws ReservationConflict
     */
    private function offer(int $ticketTypeId): array
    {
        $pool = TicketType::query()
            ->with('event:id,status,starts_at')
            ->find($ticketTypeId, ['id', 'event_id', 'quantity', 'sold', 'held', 'version']);

        if ($pool === null || ! TicketAvailability::isOnSale($pool->event->status, $pool->event->starts_at, now())) {
            return [];
        }

        $waiting = WaitlistEntry::query()
            ->where('ticket_type_id', $pool->id)
            ->where('status', WaitlistStatus::Waiting)
            ->orderBy('id')
            ->pluck('quantity', 'id')
            ->all();

        $picked = WaitlistQueue::pick($waiting, TicketAvailability::free($pool->quantity, $pool->sold, $pool->held));
        $offeredUntil = WaitlistQueue::offerEndsAt(now(), config('ticketflow.waitlist_offer_minutes'));
        $offered = [];
        $seats = 0;

        foreach ($picked as $entryId) {
            // An entry left meanwhile keeps its new status and gets no seats.
            $updated = WaitlistEntry::query()
                ->whereKey($entryId)
                ->where('status', WaitlistStatus::Waiting)
                ->update(['status' => WaitlistStatus::Offered, 'offered_until' => $offeredUntil]);

            if ($updated === 1) {
                $offered[] = $entryId;
                $seats += $waiting[$entryId];
            }
        }

        if ($seats === 0) {
            return [];
        }

        $this->holdSeats($pool, $seats);

        foreach ($offered as $entryId) {
            WaitlistOfferCreated::dispatch($entryId);
        }

        return $offered;
    }

    /**
     * The same conditional update as a reservation. Losing it rolls the offers back too.
     *
     * @throws ReservationConflict
     */
    private function holdSeats(TicketType $pool, int $seats): void
    {
        $updated = TicketType::query()
            ->whereKey($pool->id)
            ->where('version', $pool->version)
            ->whereRaw('quantity - sold - held >= ?', [$seats])
            ->update([
                'held' => $pool->held + $seats,
                'version' => $pool->version + 1,
            ]);

        if ($updated === 0) {
            throw new ReservationConflict;
        }
    }
}
