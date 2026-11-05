<?php

namespace App\Enums;

enum QueueName: string
{
    case Orders = 'orders';
    case Payments = 'payments';
    case Tickets = 'tickets';
    case Documents = 'documents';
    case Mail = 'mail';
    case Stats = 'stats';
    case Notifications = 'notifications';
    case Waitlist = 'waitlist';
    case Refunds = 'refunds';
}
