<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'startsAt' => ApiDate::format($this->starts_at),
            'endsAt' => ApiDate::format($this->ends_at),
            'status' => $this->status,
            'ticketTypes' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }
}
