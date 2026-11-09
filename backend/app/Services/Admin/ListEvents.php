<?php

namespace App\Services\Admin;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListEvents
{
    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Event>
     */
    public function handle(array $sort, int $limit, int $page): LengthAwarePaginator
    {
        [$column, $direction] = $sort;

        return Event::query()
            ->select(['id', 'organizer_id', 'title', 'status', 'venue', 'city', 'category', 'timezone', 'starts_at', 'ends_at'])
            ->with('organizer:id,name,email,role')
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }
}
