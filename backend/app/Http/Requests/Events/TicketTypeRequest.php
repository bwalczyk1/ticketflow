<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ApiFormRequest;

/**
 * The shared label of the name field describes a person, so a ticket type names its own.
 */
abstract class TicketTypeRequest extends ApiFormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('validation.attributes.ticket_type_name'),
        ];
    }
}
