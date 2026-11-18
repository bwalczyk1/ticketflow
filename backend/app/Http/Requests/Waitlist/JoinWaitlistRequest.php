<?php

namespace App\Http\Requests\Waitlist;

use App\Http\Requests\ApiFormRequest;

class JoinWaitlistRequest extends ApiFormRequest
{
    /**
     * Nobody can buy more than the limit per person, so a larger wish is invalid on its own.
     * Going over the limit together with tickets already bought is a 409.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('ticketflow.ticket_limit_per_person')],
        ];
    }
}
