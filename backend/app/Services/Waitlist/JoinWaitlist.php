<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Exceptions\Domain\AlreadyOnWaitlist;
use App\Exceptions\Domain\SalesClosed;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Exceptions\Domain\WaitlistNotNeeded;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Orders\TicketAvailability;
use App\Services\Orders\TicketLimit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class JoinWaitlist
{
    public function __construct(private TicketLimit $ticketLimit) {}

    /**
     * @throws SalesClosed
     * @throws AlreadyOnWaitlist
     * @throws WaitlistNotNeeded
     * @throws TicketLimitExceeded
     */
    public function handle(User $user, int $ticketTypeId, int $quantity): WaitlistEntry
    {
        try {
            return DB::transaction(fn () => $this->join($user, $ticketTypeId, $quantity));
        } catch (UniqueConstraintViolationException) {
            // The unique key on active entries is the last line of defence behind the check below.
            throw new AlreadyOnWaitlist;
        }
    }

    private function join(User $user, int $ticketTypeId, int $quantity): WaitlistEntry
    {
        // The same first statement as a reservation, so the tickets counted below cannot change meanwhile.
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail(['id']);

        $pool = TicketType::query()
            ->with('event:id,status,starts_at')
            ->findOrFail($ticketTypeId, ['id', 'event_id', 'quantity', 'sold', 'held']);

        TicketAvailability::ensureOnSale($pool->event->status, $pool->event->starts_at, now());

        $alreadyWaiting = WaitlistEntry::query()
            ->where('user_id', $user->id)
            ->where('ticket_type_id', $pool->id)
            ->whereIn('status', WaitlistStatus::active())
            ->exists();

        if ($alreadyWaiting) {
            throw new AlreadyOnWaitlist;
        }

        WaitlistQueue::ensureNeeded($quantity, TicketAvailability::free($pool->quantity, $pool->sold, $pool->held));

        TicketAvailability::ensureWithinLimit(
            $quantity,
            $this->ticketLimit->used($user->id, $pool->event_id),
            config('ticketflow.ticket_limit_per_person'),
        );

        $entry = new WaitlistEntry(['ticket_type_id' => $pool->id, 'quantity' => $quantity]);
        $entry->user_id = $user->id;
        $entry->save();

        return $entry;
    }
}
