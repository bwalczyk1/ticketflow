<?php

namespace App\Http\Resources;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WaitlistEntry
 */
class WaitlistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'quantity' => $this->quantity,
            /**
             * The place in the queue, starting at 1, while the entry is `WAITING`.
             *
             * @var int|null
             */
            'position' => $this->status === WaitlistStatus::Waiting ? (int) $this->queue_position : null,
            /**
             * Until when an offer can be accepted.
             *
             * @var string|null
             *
             * @format date-time
             */
            'offeredUntil' => ApiDate::format($this->offered_until),
            /**
             * The order an accepted offer became.
             *
             * @var int|null
             */
            'orderId' => $this->order_id,
            'ticketType' => [
                'id' => $this->ticketType->id,
                'name' => $this->ticketType->name,
                'price' => $this->ticketType->price,
            ],
            'event' => [
                'id' => $this->ticketType->event->id,
                'title' => $this->ticketType->event->title,
                /**
                 * @var string
                 *
                 * @format date-time
                 */
                'startsAt' => ApiDate::format($this->ticketType->event->starts_at),
            ],
            /**
             * @var string
             *
             * @format date-time
             */
            'createdAt' => ApiDate::format($this->created_at),
        ];
    }
}
