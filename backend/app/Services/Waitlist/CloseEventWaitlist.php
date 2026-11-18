<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class CloseEventWaitlist
{
    public function __construct(private WaitlistOffers $waitlistOffers) {}

    /**
     * A cancelled event sells nothing, so its queue is closed and the seats of open offers go
     * back to the pool without being offered again. A second run finds no active entries.
     */
    public function handle(int $eventId): void
    {
        $poolIds = TicketType::query()->where('event_id', $eventId)->orderBy('id')->pluck('id');

        foreach ($poolIds as $poolId) {
            DB::transaction(fn () => $this->closePool($poolId));
        }
    }

    private function closePool(int $poolId): void
    {
        $entries = WaitlistEntry::query()
            ->where('ticket_type_id', $poolId)
            ->whereIn('status', WaitlistStatus::active())
            ->lockForUpdate()
            ->get(['id', 'quantity', 'status']);

        if ($entries->isEmpty()) {
            return;
        }

        WaitlistEntry::query()->whereKey($entries->modelKeys())->update(['status' => WaitlistStatus::Cancelled]);

        $offeredSeats = (int) $entries->where('status', WaitlistStatus::Offered)->sum('quantity');

        if ($offeredSeats > 0) {
            $this->waitlistOffers->returnSeats($poolId, $offeredSeats, offerToNext: false);
        }
    }
}
