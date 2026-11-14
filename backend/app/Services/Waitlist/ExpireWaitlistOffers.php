<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExpireWaitlistOffers
{
    public function __construct(private WaitlistOffers $waitlistOffers) {}

    /**
     * @return int the number of expired offers
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();

        return WaitlistEntry::query()
            ->where('status', WaitlistStatus::Offered)
            ->where('offered_until', '<=', $now)
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $entryId) => $this->expire($entryId, $now))
            ->count();
    }

    /**
     * Decided again under the row lock, because the user may have accepted or left meanwhile.
     * The seats go to the next people in line in the same transaction.
     */
    private function expire(int $entryId, CarbonImmutable $now): bool
    {
        return DB::transaction(function () use ($entryId, $now) {
            $entry = WaitlistEntry::query()
                ->whereKey($entryId)
                ->where('status', WaitlistStatus::Offered)
                ->where('offered_until', '<=', $now)
                ->lockForUpdate()
                ->first(['id', 'ticket_type_id', 'quantity']);

            if ($entry === null) {
                return false;
            }

            WaitlistEntry::query()->whereKey($entry->id)->update(['status' => WaitlistStatus::Expired]);

            $this->waitlistOffers->returnSeats($entry->ticket_type_id, $entry->quantity);

            return true;
        });
    }
}
