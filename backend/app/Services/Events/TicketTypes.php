<?php

namespace App\Services\Events;

use App\Exceptions\Domain\EventClosed;
use App\Exceptions\Domain\PoolProtected;
use App\Exceptions\Domain\ReservationConflict;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketTypes
{
    private const MAX_ATTEMPTS = 5;

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, TicketType>
     */
    public function list(Event $event, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $event->ticketTypes()
            ->orderBy(...$sort)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }

    public function find(Event $event, int $id): TicketType
    {
        return $event->ticketTypes()->findOrFail($id);
    }

    /**
     * @param  array{name: string, price: int, quantity: int}  $data
     *
     * @throws EventClosed
     */
    public function create(Event $event, array $data): TicketType
    {
        EventPublication::ensureEditable($event->status);

        return $event->ticketTypes()->create($data)->refresh();
    }

    /**
     * Every attempt reads the pool again, and the write goes through only if no sale changed
     * the version in the meantime. A sale that wins is then checked against the new quantity.
     *
     * @param  array{name?: string, price?: int, quantity?: int}  $data
     *
     * @throws EventClosed
     * @throws PoolProtected
     * @throws ReservationConflict
     */
    public function update(Event $event, int $id, array $data): TicketType
    {
        EventPublication::ensureEditable($event->status);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $ticketType = $this->find($event, $id);

            PoolProtection::ensureQuantity($data['quantity'] ?? $ticketType->quantity, $ticketType->sold, $ticketType->held);

            $updated = TicketType::query()
                ->whereKey($ticketType->id)
                ->where('version', $ticketType->version)
                ->update([...$data, 'version' => $ticketType->version + 1]);

            if ($updated === 1) {
                return $ticketType->refresh();
            }

            usleep(random_int(10_000, 50_000));
        }

        throw new ReservationConflict;
    }

    /**
     * The condition sits in the DELETE itself, so a sale cannot slip in between a check and the write.
     *
     * @throws EventClosed
     * @throws PoolProtected
     */
    public function delete(Event $event, int $id): void
    {
        EventPublication::ensureEditable($event->status);

        $deleted = TicketType::query()
            ->whereKey($this->find($event, $id)->id)
            ->where('sold', 0)
            ->where('held', 0)
            ->delete();

        if ($deleted === 0) {
            throw new PoolProtected('A ticket type with sold or held tickets cannot be deleted');
        }
    }
}
