<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            /**
             * @var string
             *
             * @format date-time
             */
            'expiresAt' => ApiDate::format($this->expires_at),
            /**
             * @var string
             *
             * @format date-time
             */
            'createdAt' => ApiDate::format($this->created_at),
            'paymentStatus' => $this->payment?->status,
            'event' => new OrderEventResource($this->event),
            'items' => OrderItemResource::collection($this->items),
        ];
    }
}
