<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
