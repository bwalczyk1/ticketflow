<?php

namespace App\Http\Controllers\Waitlist;

use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Waitlist\JoinWaitlistRequest;
use App\Http\Requests\Waitlist\ListWaitlistRequest;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\TicketType;
use App\Services\Waitlist\JoinWaitlist;
use App\Services\Waitlist\LeaveWaitlist;
use App\Services\Waitlist\WaitlistEntries;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
