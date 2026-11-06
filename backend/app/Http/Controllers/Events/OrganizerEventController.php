<?php

namespace App\Http\Controllers\Events;

use App\Http\Requests\Events\ListOrganizerEventsRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventManagementResource;
use App\Services\Events\OrganizerEvents;
use App\Support\Pagination;
use Illuminate\Http\Response;

class OrganizerEventController extends OrganizerController
{
    public function __construct(private OrganizerEvents $events) {}

    /**
     * List own events in every status.
     *
     * @response array{data: EventManagementResource[], meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function index(ListOrganizerEventsRequest $request): array
    {
        $events = $this->events->list(
            $request->user(),
            $request->validated('status'),
            $request->sortColumn(),
            $request->limit(),
            $request->page(),
        );

        return Pagination::response($events, EventManagementResource::class);
    }

    /**
     * Create a draft event.
     */
    public function store(StoreEventRequest $request): EventManagementResource
    {
        return new EventManagementResource($this->events->create($request->user(), $request->eventData()));
    }

    /**
     * Show an own event.
     */
    public function show(int $event): EventManagementResource
    {
        return new EventManagementResource($this->ownedEvent($event)->load('ticketTypes'));
    }

    /**
     * Update an own event.
     */
    public function update(UpdateEventRequest $request, int $event): EventManagementResource
    {
        return new EventManagementResource($this->events->update($this->ownedEvent($event), $request->eventData()));
    }

    /**
     * Delete an own event without sales.
     */
    public function destroy(int $event): Response
    {
        $this->events->delete($this->ownedEvent($event));

        return response()->noContent();
    }
}
