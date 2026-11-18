<?php

namespace App\Http\Resources;

use App\Models\EventSalesSummary;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSalesSummary
 */
class TicketSalesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'eventId' => $this->event_id,
            /**
             * Seats in paid orders.
             */
            'ticketsSold' => $this->tickets_sold,
            /**
             * Total of paid orders in grosz.
             */
            'revenue' => $this->revenue,
            /**
             * Refunded amount in grosz.
             */
            'refunds' => $this->refunds,
            'checkedIn' => $this->checked_in,
            /**
             * Null until the first sale.
             *
             * @var string|null
             *
             * @format date-time
             */
            'updatedAt' => ApiDate::format($this->updated_at),
        ];
    }
}
