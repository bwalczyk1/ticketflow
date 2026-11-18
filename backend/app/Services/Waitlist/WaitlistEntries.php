<?php

namespace App\Services\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reads entries the way WaitlistEntryResource and WaitlistEntryPolicy need them.
 */
class WaitlistEntries
{
    private const COLUMNS = [
        'waitlist_entries.id',
        'waitlist_entries.user_id',
        'waitlist_entries.ticket_type_id',
        'waitlist_entries.quantity',
        'waitlist_entries.status',
        'waitlist_entries.offered_until',
        'waitlist_entries.order_id',
        'waitlist_entries.created_at',
    ];

    private const RELATIONS = [
        'ticketType:id,event_id,name,price',
        'ticketType.event:id,title,starts_at',
    ];

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, WaitlistEntry>
     */
    public function list(User $user, ?WaitlistStatus $status, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $this->query()
            ->where('waitlist_entries.user_id', $user->id)
            ->when($status, fn (Builder $query) => $query->where('waitlist_entries.status', $status))
            ->orderBy("waitlist_entries.{$sort[0]}", $sort[1])
            ->orderBy('waitlist_entries.id', $sort[1])
            ->paginate(perPage: $limit, page: $page);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function find(int|string $id): WaitlistEntry
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * The place in the queue is counted in the same query: the waiting entries of the pool that
     * joined no later than this one. Entries with an offer or closed ones no longer stand in line.
     *
     * @return Builder<WaitlistEntry>
     */
    private function query(): Builder
    {
        $ahead = WaitlistEntry::query()
            ->from('waitlist_entries as ahead')
            ->selectRaw('count(*)')
            ->whereColumn('ahead.ticket_type_id', 'waitlist_entries.ticket_type_id')
            ->where('ahead.status', WaitlistStatus::Waiting)
            ->whereColumn('ahead.id', '<=', 'waitlist_entries.id');

        return WaitlistEntry::query()
            ->select(self::COLUMNS)
            ->selectSub($ahead, 'queue_position')
            ->with(self::RELATIONS);
    }
}
