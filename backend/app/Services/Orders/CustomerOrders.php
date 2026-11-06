<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerOrders
{
    /**
     * Everything OrderResource reads.
     */
    private const RELATIONS = ['event', 'items.ticketType', 'payment'];

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(User $user, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $user->orders()
            ->with(self::RELATIONS)
            ->orderBy(...$sort)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function find(int|string $id): Order
    {
        return Order::query()->with(self::RELATIONS)->findOrFail($id);
    }
}
