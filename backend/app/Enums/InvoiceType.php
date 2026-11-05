<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Invoice = 'INVOICE';
    case Correction = 'CORRECTION';
}
