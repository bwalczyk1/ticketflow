<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'position' => $this->position,
            /**
             * @var string|null
             *
             * @format date-time
             */
            'checkedInAt' => ApiDate::format($this->checked_in_at),
            /**
             * False until the PDF has been generated in the background.
             */
            'pdfReady' => $this->pdf_path !== null,
            'orderId' => $this->orderItem->order_id,
            'ticketType' => [
                'id' => $this->orderItem->ticketType->id,
                'name' => $this->orderItem->ticketType->name,
            ],
            'event' => new OrderEventResource($this->orderItem->order->event),
        ];
    }
}
