<?php

namespace App\Http\Controllers\Events;

use App\Http\Requests\Events\ListTicketTypesRequest;
use App\Http\Requests\Events\StoreTicketTypeRequest;
use App\Http\Requests\Events\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeManagementResource;
use App\Services\Events\TicketTypes;
use App\Support\Pagination;
use Illuminate\Http\Response;

class TicketTypeController extends OrganizerController
{
    public function __construct(private TicketTypes $ticketTypes) {}

    /**
     * List the ticket types of an own event.
     */
    public function index(ListTicketTypesRequest $request, string $id): array
    {
        $ticketTypes = $this->ticketTypes->list(
            $this->ownedEvent($id),
            $request->sortColumn(),
            $request->limit(),
            $request->page(),
        );

        return Pagination::response($ticketTypes, TicketTypeManagementResource::class);
    }

    /**
     * Add a ticket type to an own event.
     *
     * `price` is in grosz, like all prices in the API.
     */
    public function store(StoreTicketTypeRequest $request, string $id): TicketTypeManagementResource
    {
        /** @status 201 */
        return new TicketTypeManagementResource($this->ticketTypes->create($this->ownedEvent($id), $request->validated()));
    }

    /**
     * Show a ticket type of an own event.
     */
    public function show(string $id, string $ticketTypeId): TicketTypeManagementResource
    {
        return new TicketTypeManagementResource($this->ticketTypes->find($this->ownedEvent($id), $ticketTypeId));
    }

    /**
     * Update a ticket type of an own event.
     *
     * `quantity` cannot go below the sold and held tickets.
     */
    public function update(UpdateTicketTypeRequest $request, string $id, string $ticketTypeId): TicketTypeManagementResource
    {
        return new TicketTypeManagementResource($this->ticketTypes->update($this->ownedEvent($id), $ticketTypeId, $request->validated()));
    }

    /**
     * Delete a ticket type without sales.
     */
    public function destroy(string $id, string $ticketTypeId): Response
    {
        $this->ticketTypes->delete($this->ownedEvent($id), $ticketTypeId);

        return response()->noContent();
    }
}
