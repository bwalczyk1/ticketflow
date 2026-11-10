<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ApiFormRequest;

class StoreTicketTypeRequest extends ApiFormRequest
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
