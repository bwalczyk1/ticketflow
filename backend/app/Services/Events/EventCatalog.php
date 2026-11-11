<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EventCatalog
{
    private const EVENT_COLUMNS = [
        'id', 'title', 'description', 'venue', 'city', 'category', 'timezone', 'starts_at', 'ends_at', 'status',
    ];

    private const TICKET_TYPES = 'ticketTypes:id,event_id,name,price,quantity,sold,held';

    /**
     * The list shows only what can still be bought, and sales end at the start of an event.
     *
     * @param  array{q?: ?string, city?: ?string, category?: ?string, from?: ?string, to?: ?string, price_max?: ?string}  $criteria
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Event>
     */
    public function search(array $criteria, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        $query = $this->published()
            ->where('starts_at', '>', now())
            ->orderBy(...$sort)
            ->orderBy('id');

        $this->applyCriteria($query, $criteria);

        return $query->paginate(perPage: $limit, page: $page);
    }

    /**
     * Unlike the list, this keeps an event that already started, because orders and tickets
     * still link to it.
     *
     * @throws ModelNotFoundException
     */
    public function find(string $id): Event
    {
        return $this->published()->findOrFail($id);
    }

    /**
     * @return Builder<Event>
     */
    private function published(): Builder
    {
        return Event::query()
            ->select(self::EVENT_COLUMNS)
            ->with(self::TICKET_TYPES)
            ->where('status', EventStatus::Published);
    }

    /**
     * @param  Builder<Event>  $query
     * @param  array{q?: ?string, city?: ?string, category?: ?string, from?: ?string, to?: ?string, price_max?: ?string}  $criteria
     */
    private function applyCriteria(Builder $query, array $criteria): void
    {
        if (isset($criteria['q'])) {
            $query->where('title', 'like', '%'.addcslashes($criteria['q'], '\\%_').'%');
        }

        if (isset($criteria['city'])) {
            $query->where('city', $criteria['city']);
        }

        if (isset($criteria['category'])) {
            $query->where('category', $criteria['category']);
        }

        if (isset($criteria['from'])) {
            $query->where('starts_at', '>=', CarbonImmutable::parse($criteria['from'])->utc());
        }

        if (isset($criteria['to'])) {
            $query->where('starts_at', '<=', CarbonImmutable::parse($criteria['to'])->utc());
        }

        if (isset($criteria['price_max'])) {
            $query->whereHas('ticketTypes', fn (Builder $ticketTypes) => $ticketTypes->where('price', '<=', $criteria['price_max']));
        }
    }
}
