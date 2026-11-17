<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'city' => $this->city,
            'category' => $this->category,
            'timezone' => $this->timezone,
            /**
             * @var string
             *
             * @format date-time
             */
            'startsAt' => $this->starts_at?->toIso8601ZuluString(),
            /**
             * @var string
             *
             * @format date-time
             */
            'endsAt' => $this->ends_at?->toIso8601ZuluString(),
            'status' => $this->status,
            'ticketTypes' => TicketTypeManagementResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }
}
