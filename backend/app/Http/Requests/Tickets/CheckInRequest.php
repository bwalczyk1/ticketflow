<?php

namespace App\Http\Requests\Tickets;

use App\Http\Requests\ApiFormRequest;

class CheckInRequest extends ApiFormRequest
{
    /**
     * Any scanned text is accepted: a code that matches no ticket of the event is a 404, not a 400.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * The ticket code from the QR code, a UUID.
             */
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
