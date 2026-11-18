<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Events\OrganizerController;
use App\Http\Resources\TicketSalesResource;
use App\Services\Tickets\SalesSummary;

class TicketSalesController extends OrganizerController
{
    /**
     * Show the sales summary of an own event.
     *
     * The summary is counted again in the background after every payment, refund and check-in,
     * so it can lag a few seconds behind. Amounts are in grosz.
     */
    public function show(string $id, SalesSummary $salesSummary): TicketSalesResource
    {
        return new TicketSalesResource($salesSummary->forEvent($this->ownedEvent($id)));
    }
}
