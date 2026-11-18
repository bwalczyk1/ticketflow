<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ListCatalogEventsRequest;
use App\Http\Resources\EventResource;
use App\Services\Events\EventCatalog;
use App\Support\Pagination;

class CatalogController extends Controller
{
    public function __construct(private EventCatalog $catalog) {}

    /**
     * List published events that have not started yet.
     *
     * `price_max` is in grosz, like all prices in the API.
     *
     * @unauthenticated
     */
    public function index(ListCatalogEventsRequest $request): array
    {
        $events = $this->catalog->search(
            $request->criteria(),
            $request->sortColumn(),
            $request->limit(),
            $request->page(),
        );

        return Pagination::response($events, EventResource::class);
    }

    /**
     * Show a published event with its ticket types and their availability, also after it started.
     *
     * @unauthenticated
     */
    public function show(string $id): EventResource
    {
        return new EventResource($this->catalog->find($id));
    }
}
