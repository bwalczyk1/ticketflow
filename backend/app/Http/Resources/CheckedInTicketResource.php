<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the gate shows after a scan: the ticket, its pool and the name of the buyer.
 *
 * @mixin Ticket
 */
class CheckedInTicketResource extends JsonResource
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
            /**
             * @var string
             *
             * @format date-time
             */
            'checkedInAt' => $this->checked_in_at?->toIso8601ZuluString(),
            'ticketTypeName' => $this->orderItem->ticketType->name,
            'holderName' => $this->orderItem->order->user->name,
        ];
    }
}
