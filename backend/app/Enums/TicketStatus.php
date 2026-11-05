<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Valid = 'VALID';
    case Used = 'USED';
    case Void = 'VOID';
}
