<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;

class WaitlistEntryPolicy
{
    public function view(User $user, WaitlistEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
