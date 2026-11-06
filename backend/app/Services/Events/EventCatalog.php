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
    /**
     * @param  array{q?: ?string, city?: ?string, category?: ?string, from?: ?string, to?: ?string, price_max?: ?string}  $criteria
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Event>
     */
    public function search(array $criteria, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        $query = $this->upcoming()
            ->orderBy(...$sort)
            ->orderBy('id');

        $this->applyCriteria($query, $criteria);

        return $query->paginate(perPage: $limit, page: $page);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function find(string $id): Event
    {
        return $this->upcoming()->findOrFail($id);
    }

    /**
     * The catalog shows only what can still be bought, and sales end at the start of an event.
     *
     * @return Builder<Event>
     */
    private function upcoming(): Builder
    {
        return Event::query()
            ->with('ticketTypes')
            ->where('status', EventStatus::Published)
            ->where('starts_at', '>', now());
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
