<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiFormRequest;

class StoreOrderRequest extends ApiFormRequest
{
    /**
     * Quantity has no upper bound here: going over the limit per person is a 409, not a 400.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'eventId' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticketTypeId' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
