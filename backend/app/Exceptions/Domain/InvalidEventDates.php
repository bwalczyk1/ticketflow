<?php

namespace App\Exceptions\Domain;

/**
 * A partial update can only be compared with the stored dates in the service,
 * so this answers exactly like a failed Form Request.
 */
class InvalidEventDates extends DomainException
{
    public function __construct(string $message = 'Invalid request data')
    {
        parent::__construct($message, 'VALIDATION_ERROR', 400, [
            ['field' => 'endsAt', 'message' => 'The end must be after the start.'],
        ]);
    }
}
