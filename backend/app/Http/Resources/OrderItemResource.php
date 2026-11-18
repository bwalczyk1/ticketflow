<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticketTypeId' => $this->ticket_type_id,
            'name' => $this->ticketType->name,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unit_price,
        ];
    }
}
