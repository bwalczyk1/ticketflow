<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Auth\Access\Response;

class WaitlistEntryPolicy
{
    /**
     * Another user's entry answers 404, like an order.
     */
    public function view(User $user, WaitlistEntry $entry): Response
    {
        return $entry->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
