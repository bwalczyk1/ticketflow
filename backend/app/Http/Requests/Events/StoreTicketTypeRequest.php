<?php

namespace App\Http\Requests\Events;

class StoreTicketTypeRequest extends TicketTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
