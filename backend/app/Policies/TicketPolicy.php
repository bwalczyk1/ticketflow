<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Another user's ticket answers 404, like an order. Needs orderItem.order with user_id loaded.
     */
    public function view(User $user, Ticket $ticket): Response
    {
        return $ticket->orderItem->order->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
