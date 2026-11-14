<?php

namespace App\Http\Controllers\Orders;

use App\Exceptions\Domain\InvoiceNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Documents\InvoiceNumbering;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\CustomerOrders;
use App\Services\Orders\ReserveTickets;
use App\Services\Payments\StartPayment;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(private CustomerOrders $orders) {}

    /**
     * List own orders.
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
     * Download the invoice PDF of an own order.
     *
     * The invoice is issued in the background after the payment, so for a moment it is not there yet.
     *
     * @throws InvoiceNotFound
     */
    public function invoice(string $id): StreamedResponse
    {
        $invoice = $this->orders->invoice($this->ownOrder($id));
        $filename = 'invoice-'.str_replace('/', '-', InvoiceNumbering::label($invoice->year, $invoice->number)).'.pdf';

        return Storage::disk('local')->download($invoice->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Pay for an own order.
     *
     * Payments are processed in the background: the answer is 202 and the result appears in
     * `status` and `paymentStatus` of the order.
     */
    public function pay(string $id, StartPayment $startPayment): JsonResponse
    {
        $order = $this->ownOrder($id);

        $startPayment->handle($order);

        return (new OrderResource($this->orders->find($order->id)))->response()->setStatusCode(202);
    }

    /**
     * Cancel an own order before paying.
     *
     * The held seats go back to the pool at once.
     */
    public function cancel(string $id, CancelOrder $cancelOrder): OrderResource
    {
        $order = $this->ownOrder($id);

        $cancelOrder->handle($order);

        return new OrderResource($this->orders->find($order->id));
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
