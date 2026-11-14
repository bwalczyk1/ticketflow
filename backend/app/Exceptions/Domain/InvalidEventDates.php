<?php

namespace App\Exceptions\Domain;

/**
 * Dates can only be compared with the stored event in the service, so this answers exactly like
 * a failed Form Request, with the field message in the language of the request.
 */
class InvalidEventDates extends DomainException
{
    public function __construct(string $field = 'endsAt', string $messageKey = 'validation.event_ends_after_start')
    {
        parent::__construct('Invalid request data', 'VALIDATION_ERROR', 400, [
            ['field' => $field, 'message' => $messageKey],
        ]);
    }
}
