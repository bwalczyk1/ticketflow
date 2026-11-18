<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvoiceNotFound;
use App\Models\Invoice;
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
        'invoice:id,order_id,year,number,pdf_path,created_at',
    ];

    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(User $user, ?OrderStatus $status, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return $user->orders()
            ->select(self::COLUMNS)
            ->with(self::RELATIONS)
            ->when($status, fn ($query) => $query->where('status', $status))
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

    /**
     * The invoice is saved before its PDF is generated, so until then there is nothing to hand out.
     *
     * @throws InvoiceNotFound
     */
    public function invoice(Order $order): Invoice
    {
        if ($order->invoice?->pdf_path === null) {
            throw new InvoiceNotFound;
        }

        return $order->invoice;
    }
}
