<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
