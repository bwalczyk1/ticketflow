<?php

namespace App\Http\Requests\Events;

class UpdateEventRequest extends EventRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'venue' => ['sometimes', 'string', 'max:160'],
            'city' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'timezone' => ['sometimes', 'timezone:all'],
            'startsAt' => ['sometimes', 'date', new OffsetDate],
            'endsAt' => ['sometimes', 'date', new OffsetDate],
        ];
    }
}
