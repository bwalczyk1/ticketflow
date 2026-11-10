<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class AdminEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'venue' => $this->venue,
            'city' => $this->city,
            'category' => $this->category,
            'timezone' => $this->timezone,
            'startsAt' => ApiDate::format($this->starts_at),
            'endsAt' => ApiDate::format($this->ends_at),
            'organizer' => new UserResource($this->organizer),
        ];
    }
}
