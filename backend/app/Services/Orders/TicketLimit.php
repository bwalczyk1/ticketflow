<?php

namespace App\Services\Orders;

use App\Data\PurchaseLimit;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The per-person ticket limit of an event, counted the same way for reserving and for showing it.
 */
class TicketLimit
{
    /**
     * Tickets of orders that still count as bought or being bought. A pending order past its
     * expires_at counts too: it holds seats until released and its payment may still succeed.
     */
    public function used(int $userId, int $eventId): int
    {
        return (int) OrderItem::query()
            ->whereHas('order', fn (Builder $orders) => $orders
                ->where('user_id', $userId)
                ->where('event_id', $eventId)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Paid]))
            ->sum('quantity');
    }

    /**
     * Only an attendee buys tickets, so nobody else has a limit to show.
     */
    public function forViewer(?User $user, int $eventId): ?PurchaseLimit
    {
        if ($user?->role !== Role::Attendee) {
            return null;
        }

        return new PurchaseLimit(config('ticketflow.ticket_limit_per_person'), $this->used($user->id, $eventId));
    }
}
