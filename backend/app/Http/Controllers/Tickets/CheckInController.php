<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Events\OrganizerController;
use App\Http\Requests\Tickets\CheckInRequest;
use App\Http\Resources\CheckedInTicketResource;
use App\Services\Tickets\CheckInTicket;

class CheckInController extends OrganizerController
{
    /**
     * Check a ticket in at the entrance of an own event.
     *
     * Works only on the day of the event, counted in the event time zone. A ticket gets in once:
     * a second scan answers `TICKET_ALREADY_USED` with the time of the first one in `details`
     * (`field` = `checkedInAt`).
     *
     * @return array{ticket: CheckedInTicketResource}
     */
    public function store(CheckInRequest $request, string $id, CheckInTicket $checkInTicket): array
    {
        $ticket = $checkInTicket->handle($this->ownedEvent($id), $request->validated('code'));

        return ['ticket' => new CheckedInTicketResource($ticket)];
    }
}
