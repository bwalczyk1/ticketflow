<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Events\Catalog\EventCancelled;
use App\Exceptions\Domain\EventClosed;
use App\Exceptions\Domain\EventHasSales;
use App\Exceptions\Domain\EventNotPublishable;
use App\Exceptions\Domain\InvalidEventDates;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrganizerEvents
{
    private const TICKET_TYPES = 'ticketTypes:id,event_id,name,price,quantity,sold,held';

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Event>
     */
    public function list(User $organizer, ?string $status, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $organizer->organizedEvents()
            ->select(['id', 'organizer_id', 'title', 'description', 'venue', 'city', 'category', 'timezone', 'starts_at', 'ends_at', 'status'])
            ->with(self::TICKET_TYPES)
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

        EventPublication::ensureStartsInFuture($event->starts_at, now()->toImmutable());
        EventPublication::ensureDatesInOrder($event->starts_at, $event->ends_at);

        $organizer->organizedEvents()->save($event);

        return $event->load(self::TICKET_TYPES);
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

        if ($event->isDirty('starts_at')) {
            EventPublication::ensureStartsInFuture($event->starts_at, now()->toImmutable());
        }

        EventPublication::ensureDatesInOrder($event->starts_at, $event->ends_at);

        $event->save();

        return $event->load(self::TICKET_TYPES);
    }

    /**
     * The pools stay locked until the delete commits, so a sale cannot slip in after the check.
     *
     * @throws EventHasSales
     */
    public function delete(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $ticketTypes = $event->ticketTypes()->lockForUpdate()->get(['id', 'sold', 'held']);

            EventPublication::ensureDeletable((int) $ticketTypes->sum('sold'), (int) $ticketTypes->sum('held'));

            $event->delete();
        });
    }

    /**
     * @throws InvalidStatusTransition
     * @throws EventNotPublishable
     */
    public function publish(Event $event): Event
    {
        EventPublication::ensurePublishable(
            $event->status,
            $event->ticketTypes()->count(),
            $event->starts_at,
            now()->toImmutable(),
        );

        $this->changeStatus($event, EventStatus::Published);

        return $event->load(self::TICKET_TYPES);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function cancel(Event $event): Event
    {
        $this->changeStatus($event, EventStatus::Cancelled);

        EventCancelled::dispatch($event->id);

        return $event->load(self::TICKET_TYPES);
    }

    /**
     * Compare-and-set on the status column, so of two parallel requests only one gets through.
     *
     * @throws InvalidStatusTransition
     */
    private function changeStatus(Event $event, EventStatus $status): void
    {
        if (! $event->status->canTransitionTo($status)) {
            throw new InvalidStatusTransition;
        }

        $updated = Event::query()
            ->whereKey($event->id)
            ->where('status', $event->status)
            ->update(['status' => $status]);

        if ($updated === 0) {
            throw new InvalidStatusTransition;
        }

        $event->status = $status;
        $event->syncOriginalAttribute('status');
    }
}
