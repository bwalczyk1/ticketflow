<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TicketFixtures
{
    public const PRICE = 5000;

    /**
     * A paid order the way a settled payment leaves it: the seats are sold and the payment succeeded.
     *
     * @param  list<int>  $quantities  one pool of the event per entry
     */
    public static function paidOrder(array $quantities = [2], ?User $user = null, ?Event $event = null): Order
    {
        $event ??= Event::factory()->create();

        $order = Order::factory()
            ->for($user ?? User::factory()->create())
            ->paid()
            ->create(['event_id' => $event->id, 'total' => array_sum($quantities) * self::PRICE]);

        foreach ($quantities as $quantity) {
            $pool = TicketType::factory()->for($event)->create(['price' => self::PRICE, 'sold' => $quantity]);

            OrderItem::factory()->for($order)->create([
                'ticket_type_id' => $pool->id,
                'quantity' => $quantity,
                'unit_price' => self::PRICE,
            ]);
        }

        Payment::factory()->for($order)->succeeded()->create(['amount' => $order->total]);

        return $order;
    }

    /**
     * Tickets of the order in the order of their items and positions.
     *
     * @return Collection<int, Ticket>
     */
    public static function ticketsOf(Order $order): Collection
    {
        return Ticket::query()
            ->whereIn('order_item_id', $order->items()->pluck('id'))
            ->orderBy('order_item_id')
            ->orderBy('position')
            ->get();
    }

    /**
     * One ticket for every seat of the order, as IssueTickets leaves them.
     *
     * @return Collection<int, Ticket>
     */
    public static function issueTickets(Order $order): Collection
    {
        foreach ($order->items()->get() as $item) {
            for ($position = 1; $position <= $item->quantity; $position++) {
                Ticket::factory()->for($item)->create(['position' => $position]);
            }
        }

        return self::ticketsOf($order);
    }
}
