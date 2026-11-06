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
     *
     * @response array{data: TicketTypeManagementResource[], meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function index(ListTicketTypesRequest $request, int $event): array
    {
        $ticketTypes = $this->ticketTypes->list(
            $this->ownedEvent($event),
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
    public function store(StoreTicketTypeRequest $request, int $event): TicketTypeManagementResource
    {
        return new TicketTypeManagementResource($this->ticketTypes->create($this->ownedEvent($event), $request->validated()));
    }

    /**
     * Show a ticket type of an own event.
     */
    public function show(int $event, int $ticketType): TicketTypeManagementResource
    {
        return new TicketTypeManagementResource($this->ticketTypes->find($this->ownedEvent($event), $ticketType));
    }

    /**
     * Update a ticket type of an own event.
     *
     * `quantity` cannot go below the sold and held tickets.
     */
    public function update(UpdateTicketTypeRequest $request, int $event, int $ticketType): TicketTypeManagementResource
    {
        return new TicketTypeManagementResource($this->ticketTypes->update($this->ownedEvent($event), $ticketType, $request->validated()));
    }

    /**
     * Delete a ticket type without sales.
     */
    public function destroy(int $event, int $ticketType): Response
    {
        $this->ticketTypes->delete($this->ownedEvent($event), $ticketType);

        return response()->noContent();
    }
}
