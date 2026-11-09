<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Organizer = 'ORGANIZER';
    case Attendee = 'ATTENDEE';
}
