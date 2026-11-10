<?php

namespace App\Http\Controllers\Events;

use App\Http\Requests\Events\ListOrganizerEventsRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventManagementResource;
use App\Services\Events\OrganizerEvents;
use App\Support\Pagination;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;

class OrganizerEventController extends OrganizerController
{
    public function __construct(private OrganizerEvents $events) {}

    /**
     * List own events in every status.
     *
     * @throws AuthorizationException
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
     *
     * @throws AuthorizationException
     */
    public function store(StoreEventRequest $request): EventManagementResource
    {
        /** @status 201 */
        return new EventManagementResource($this->events->create($request->user(), $request->eventData()));
    }

    /**
     * Show an own event.
     */
    public function show(string $id): EventManagementResource
    {
        return new EventManagementResource($this->ownedEvent($id)->load('ticketTypes:id,event_id,name,price,quantity,sold,held'));
    }

    /**
     * Update an own event.
     */
    public function update(UpdateEventRequest $request, string $id): EventManagementResource
    {
        return new EventManagementResource($this->events->update($this->ownedEvent($id), $request->eventData()));
    }

    /**
     * Delete an own event without sales.
     */
    public function destroy(string $id): Response
    {
        $this->events->delete($this->ownedEvent($id));

        return response()->noContent();
    }

    /**
     * Publish a draft event.
     */
    public function publish(string $id): EventManagementResource
    {
        return new EventManagementResource($this->events->publish($this->ownedEvent($id)));
    }

    /**
     * Cancel a published event.
     */
    public function cancel(string $id): EventManagementResource
    {
        return new EventManagementResource($this->events->cancel($this->ownedEvent($id)));
    }
}
