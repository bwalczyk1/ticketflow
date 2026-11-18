<?php

namespace App\Http\Requests\Events;

class UpdateTicketTypeRequest extends TicketTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
