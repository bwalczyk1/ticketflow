<?php

namespace App\Http\Requests\Events;

class StoreEventRequest extends EventRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'timezone' => ['sometimes', 'timezone:all'],
            'startsAt' => ['required', 'date', new OffsetDate],
            'endsAt' => ['required', 'date', new OffsetDate],
        ];
    }
}
