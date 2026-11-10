<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerOrders
{
    /**
     * Everything OrderResource and OrderPolicy read.
     */
    private const COLUMNS = ['id', 'user_id', 'event_id', 'status', 'total', 'expires_at', 'created_at'];

    private const RELATIONS = [
        'event:id,title,venue,city,timezone,starts_at',
        'items:id,order_id,ticket_type_id,quantity,unit_price',
        'items.ticketType:id,name',
        'payment:id,order_id,status',
    ];

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(User $user, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $user->orders()
            ->select(self::COLUMNS)
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
        return Order::query()->with(self::RELATIONS)->findOrFail($id, self::COLUMNS);
    }
}
