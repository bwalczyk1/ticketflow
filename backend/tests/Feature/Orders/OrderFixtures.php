<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;

class OrderFixtures
{
    /**
     * An order whose seats are counted in held, the way a reservation leaves them.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function holding(TicketType $pool, int $quantity = 2, ?User $user = null, array $attributes = []): Order
    {
        $order = Order::factory()
            ->for($user ?? User::factory()->create())
            ->create(['event_id' => $pool->event_id, ...$attributes]);

        OrderItem::factory()->for($order)->create(['ticket_type_id' => $pool->id, 'quantity' => $quantity]);

        TicketType::query()->whereKey($pool->id)->increment('held', $quantity);

        return $order;
    }
}
