<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Needs orderItem.order with user_id loaded.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->orderItem->order->user_id === $user->id;
    }
}
