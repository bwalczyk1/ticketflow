<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class OrderEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'venue' => $this->venue,
            'city' => $this->city,
            'timezone' => $this->timezone,
            /**
             * @var string
             *
             * @format date-time
             */
            'startsAt' => ApiDate::format($this->starts_at),
        ];
    }
}
