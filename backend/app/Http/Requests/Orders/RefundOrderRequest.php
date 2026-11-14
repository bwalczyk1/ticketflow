<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiFormRequest;

class RefundOrderRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Why the attendee gives the tickets back, printed on the correction invoice.
             */
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
