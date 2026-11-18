<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class LeaveWaitlist
{
    public function __construct(private WaitlistOffers $waitlistOffers) {}

    /**
     * Leaving with an open offer hands its seats to the next people in line in the same transaction.
     *
     * @throws ModelNotFoundException
     */
    public function handle(User $user, int $ticketTypeId): void
    {
        DB::transaction(function () use ($user, $ticketTypeId) {
            $entry = WaitlistEntry::query()
                ->where('user_id', $user->id)
                ->where('ticket_type_id', $ticketTypeId)
                ->whereIn('status', WaitlistStatus::active())
                ->lockForUpdate()
                ->first(['id', 'ticket_type_id', 'quantity', 'status']);

            if ($entry === null) {
                throw (new ModelNotFoundException)->setModel(WaitlistEntry::class);
            }

            WaitlistEntry::query()->whereKey($entry->id)->update(['status' => WaitlistStatus::Cancelled]);

            if ($entry->status === WaitlistStatus::Offered) {
                $this->waitlistOffers->returnSeats($entry->ticket_type_id, $entry->quantity);
            }
        });
    }
}
