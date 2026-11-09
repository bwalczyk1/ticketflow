<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\ReservationConflict;
use App\Exceptions\Domain\SalesClosed;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Exceptions\Domain\TicketsSoldOut;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

class ReserveTickets
{
    private const MYSQL_DEADLOCK = 1213;

    /**
     * The loop wraps the whole transaction: under REPEATABLE READ a retry inside one transaction
     * would read the same snapshot and lose again, so every attempt starts a new one.
     *
     * @param  list<array{ticketTypeId: int, quantity: int}>  $items
     *
     * @throws ModelNotFoundException
     * @throws SalesClosed
     * @throws TicketLimitExceeded
     * @throws TicketsSoldOut
     * @throws ReservationConflict
     */
    public function handle(User $user, int $eventId, array $items): Order
    {
        // Ascending ids give every transaction the same lock order, which prevents most deadlocks.
        $items = collect($items)->sortBy('ticketTypeId')->values()->all();
        $attempts = config('ticketflow.reservation_attempts');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(fn () => $this->reserve($user, $eventId, $items));
            } catch (ReservationConflict) {
                // Another client changed a pool first, so the next attempt reads it again.
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== self::MYSQL_DEADLOCK) {
                    throw $e;
                }
            }

            if ($attempt < $attempts) {
                Sleep::for(random_int(10, 50))->milliseconds();
            }
        }

        $this->ensureSeatsRemain($items);

        throw new ReservationConflict;
    }

    /**
     * @param  list<array{ticketTypeId: int, quantity: int}>  $items
     */
    private function reserve(User $user, int $eventId, array $items): Order
    {
        // This must stay the first statement of the transaction. The snapshot starts at the first
        // plain SELECT, so a read before the lock would hide orders committed while waiting for it.
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail(['id']);

        $event = Event::query()->findOrFail($eventId, ['id', 'status', 'starts_at']);
        $ticketTypes = $this->ticketTypes($event, $items);

        TicketAvailability::ensureOnSale($event->status, $event->starts_at, now());

        TicketAvailability::ensureWithinLimit(
            array_sum(array_column($items, 'quantity')),
            $this->activeTickets($user, $event),
            config('ticketflow.ticket_limit_per_person'),
        );

        foreach ($items as $item) {
            $this->holdSeats($ticketTypes[$item['ticketTypeId']], $item['quantity']);
        }

        return $this->createOrder($user, $event, $ticketTypes, $items);
    }

    /**
     * @param  list<array{ticketTypeId: int, quantity: int}>  $items
     * @return Collection<int, TicketType>
     */
    private function ticketTypes(Event $event, array $items): Collection
    {
        $ids = array_column($items, 'ticketTypeId');

        $ticketTypes = $event->ticketTypes()
            ->whereIn('id', $ids)
            ->get(['id', 'price', 'quantity', 'sold', 'held', 'version'])
            ->keyBy('id');

        if ($ticketTypes->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(TicketType::class, $ids);
        }

        return $ticketTypes;
    }

    /**
     * Tickets of orders that still count as bought or being bought. A pending order past its
     * expires_at counts too: it holds seats until released and its payment may still succeed.
     */
    private function activeTickets(User $user, Event $event): int
    {
        return (int) OrderItem::query()
            ->whereHas('order', fn (Builder $orders) => $orders
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Paid]))
            ->sum('quantity');
    }

    /**
     * @throws TicketsSoldOut
     * @throws ReservationConflict
     */
    private function holdSeats(TicketType $ticketType, int $quantity): void
    {
        TicketAvailability::ensureAvailable($quantity, $ticketType->quantity, $ticketType->sold, $ticketType->held);

        $updated = TicketType::query()
            ->whereKey($ticketType->id)
            ->where('version', $ticketType->version)
            ->whereRaw('quantity - sold - held >= ?', [$quantity])
            ->update([
                'held' => $ticketType->held + $quantity,
                'version' => $ticketType->version + 1,
            ]);

        if ($updated === 0) {
            throw new ReservationConflict;
        }
    }

    /**
     * @param  Collection<int, TicketType>  $ticketTypes
     * @param  list<array{ticketTypeId: int, quantity: int}>  $items
     */
    private function createOrder(User $user, Event $event, Collection $ticketTypes, array $items): Order
    {
        $lines = array_map(fn (array $item) => [
            'ticket_type_id' => $item['ticketTypeId'],
            'quantity' => $item['quantity'],
            'unit_price' => $ticketTypes[$item['ticketTypeId']]->price,
        ], $items);

        $order = $user->orders()->create([
            'event_id' => $event->id,
            'expires_at' => TicketAvailability::holdEndsAt(now(), config('ticketflow.hold_minutes')),
            'total' => collect($lines)->sum(fn (array $line) => $line['quantity'] * $line['unit_price']),
        ]);

        $order->items()->createMany($lines);

        return $order;
    }

    /**
     * Tells a real sell-out from a pool that was only too busy. Every attempt lost, so the last
     * read may be stale; this one runs outside any transaction and sees the committed state.
     *
     * @param  list<array{ticketTypeId: int, quantity: int}>  $items
     *
     * @throws TicketsSoldOut
     */
    private function ensureSeatsRemain(array $items): void
    {
        $ticketTypes = TicketType::query()
            ->findMany(array_column($items, 'ticketTypeId'), ['id', 'quantity', 'sold', 'held'])
            ->keyBy('id');

        foreach ($items as $item) {
            $ticketType = $ticketTypes[$item['ticketTypeId']];

            TicketAvailability::ensureAvailable($item['quantity'], $ticketType->quantity, $ticketType->sold, $ticketType->held);
        }
    }
}
