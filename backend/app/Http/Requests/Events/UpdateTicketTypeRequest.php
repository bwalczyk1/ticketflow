<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ApiFormRequest;

class UpdateTicketTypeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'price' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
