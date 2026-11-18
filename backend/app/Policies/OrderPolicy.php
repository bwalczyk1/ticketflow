<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Another user's order answers 404, not 403, because its existence is already information.
     */
    public function view(User $user, Order $order): Response
    {
        return $order->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
