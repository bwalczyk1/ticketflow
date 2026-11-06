<?php

namespace App\Services\Events;

use App\Exceptions\Domain\EventClosed;
use App\Exceptions\Domain\EventHasSales;
use App\Exceptions\Domain\InvalidEventDates;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrganizerEvents
{
    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Event>
     */
    public function list(User $organizer, ?string $status, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $organizer->organizedEvents()
            ->with('ticketTypes')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy(...$sort)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidEventDates
     */
    public function create(User $organizer, array $data): Event
    {
        $event = new Event($data);

        EventPublication::ensureDatesInOrder($event->starts_at, $event->ends_at);

        $organizer->organizedEvents()->save($event);

        return $event->load('ticketTypes');
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws EventClosed
     * @throws InvalidEventDates
     */
    public function update(Event $event, array $data): Event
    {
        EventPublication::ensureEditable($event->status);

        $event->fill($data);

        EventPublication::ensureDatesInOrder($event->starts_at, $event->ends_at);

        $event->save();

        return $event->load('ticketTypes');
    }

    /**
     * The pools stay locked until the delete commits, so a sale cannot slip in after the check.
     *
     * @throws EventHasSales
     */
    public function delete(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $ticketTypes = $event->ticketTypes()->lockForUpdate()->get();

            EventPublication::ensureDeletable((int) $ticketTypes->sum('sold'), (int) $ticketTypes->sum('held'));

            $event->delete();
        });
    }
}
