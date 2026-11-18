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
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'venue' => ['sometimes', 'required', 'string', 'max:160'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'string', 'max:50'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'startsAt' => ['sometimes', 'required', 'date', new OffsetDate],
            'endsAt' => ['sometimes', 'required', 'date', new OffsetDate],
        ];
    }
}
