<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'WAITING';
    case Offered = 'OFFERED';
    case Converted = 'CONVERTED';
    case Expired = 'EXPIRED';
}
