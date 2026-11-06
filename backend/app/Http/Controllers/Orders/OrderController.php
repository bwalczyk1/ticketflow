<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\Orders\CustomerOrders;
use App\Services\Orders\ReserveTickets;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private CustomerOrders $orders) {}

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
}
