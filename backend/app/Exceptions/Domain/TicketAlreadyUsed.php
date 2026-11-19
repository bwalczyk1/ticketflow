<?php

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;

class TicketAlreadyUsed extends DomainException
{
    /**
     * The time of the first check-in goes to the details, so the gate can tell a copied ticket
     * from one scanned twice by mistake.
     */
    public function __construct(?CarbonInterface $checkedInAt = null, string $message = 'This ticket has already been used')
    {
        $details = $checkedInAt === null ? [] : [['field' => 'checkedInAt', 'message' => $checkedInAt->toIso8601ZuluString()]];

        parent::__construct($message, 'TICKET_ALREADY_USED', 409, $details);
    }
}
