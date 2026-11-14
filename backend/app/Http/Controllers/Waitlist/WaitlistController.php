<?php

namespace App\Http\Controllers\Waitlist;

use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Waitlist\JoinWaitlistRequest;
use App\Http\Requests\Waitlist\ListWaitlistRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use App\Services\Orders\CustomerOrders;
use App\Services\Waitlist\AcceptWaitlistOffer;
use App\Services\Waitlist\JoinWaitlist;
use App\Services\Waitlist\LeaveWaitlist;
use App\Services\Waitlist\WaitlistEntries;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WaitlistController extends Controller
{
    public function __construct(private WaitlistEntries $entries) {}

    /**
     * List own waitlist entries.
     */
    public function index(ListWaitlistRequest $request): array
    {
        $entries = $this->entries->list(
            $request->user(),
            $request->enum('status', WaitlistStatus::class),
            $request->sortColumn(),
            $request->limit(),
            $request->page(),
        );

        return Pagination::response($entries, WaitlistEntryResource::class);
    }

    /**
     * Join the waitlist of a sold out pool.
     *
     * Seats that come free go to the queue first, in the order of joining, as an offer valid for
     * 30 minutes. The queue is only for seats that cannot be bought now: while enough seats are
     * free, the answer is `WAITLIST_NOT_NEEDED`.
     */
    public function store(JoinWaitlistRequest $request, string $id, JoinWaitlist $joinWaitlist): JsonResponse
    {
        $pool = TicketType::query()->findOrFail($id, ['id']);
        $entry = $joinWaitlist->handle($request->user(), $pool->id, $request->integer('quantity'));

        return (new WaitlistEntryResource($this->entries->find($entry->id)))->response()->setStatusCode(201);
    }

    /**
     * Accept an own waitlist offer.
     *
     * Turns the offer into a `PENDING` order, the same as `POST /orders` creates, which holds the
     * seats for 15 minutes and is paid with `POST /orders/{id}/pay`. Amounts are in grosz.
     */
    public function accept(Request $request, string $id, AcceptWaitlistOffer $acceptOffer, CustomerOrders $orders): JsonResponse
    {
        $entry = $this->entries->find($id);

        // Another user's entry answers 404 like an order, because its existence is already information.
        if (Gate::denies('view', $entry)) {
            throw (new ModelNotFoundException)->setModel(WaitlistEntry::class, [$id]);
        }

        $order = $acceptOffer->handle($request->user(), $entry->id);

        return (new OrderResource($orders->find($order->id)))->response()->setStatusCode(201);
    }

    /**
     * Leave the waitlist of a pool.
     *
     * Seats of an open offer go to the next person in line.
     */
    public function destroy(Request $request, string $id, LeaveWaitlist $leaveWaitlist): Response
    {
        $pool = TicketType::query()->findOrFail($id, ['id']);

        $leaveWaitlist->handle($request->user(), $pool->id);

        return response()->noContent();
    }
}
