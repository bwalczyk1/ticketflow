<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\CustomerOrders;
use App\Services\Orders\ReserveTickets;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private CustomerOrders $orders) {}

    /**
     * List own orders.
     *
     * @return array{data: list<OrderResource>, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function index(ListOrdersRequest $request): array
    {
        $orders = $this->orders->list($request->user(), $request->sortColumn(), $request->limit(), $request->page());

        return Pagination::response($orders, OrderResource::class);
    }

    /**
     * Reserve tickets.
     *
     * Creates a `PENDING` order that holds the seats for 15 minutes. All amounts are in grosz.
     */
    public function store(StoreOrderRequest $request, ReserveTickets $reserveTickets): JsonResponse
    {
        $order = $reserveTickets->handle($request->user(), $request->integer('eventId'), $request->validated('items'));

        return (new OrderResource($this->orders->find($order->id)))->response()->setStatusCode(201);
    }

    /**
     * Show an own order.
     */
    public function show(string $id): OrderResource
    {
        return new OrderResource($this->ownOrder($id));
    }

    /**
     * Another user's order answers 404, not 403, because its existence is already information.
     *
     * @throws ModelNotFoundException
     */
    private function ownOrder(string $id): Order
    {
        $order = $this->orders->find($id);

        if (Gate::denies('view', $order)) {
            throw (new ModelNotFoundException)->setModel(Order::class, [$id]);
        }

        return $order;
    }
}
