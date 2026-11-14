<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\Domain\TicketPdfNotReady;
use App\Exceptions\Domain\TicketVoid;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AttendeeTickets
{
    /**
     * Everything TicketResource and TicketPolicy read.
     */
    private const COLUMNS = ['id', 'order_item_id', 'code', 'status', 'position', 'checked_in_at', 'pdf_path', 'created_at'];

    private const RELATIONS = [
        'orderItem:id,order_id,ticket_type_id',
        'orderItem.ticketType:id,name',
        'orderItem.order:id,user_id,event_id',
        'orderItem.order.event:id,title,venue,city,timezone,starts_at',
    ];

    /**
     * @param  array{orderId: ?int, status: ?string}  $criteria
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function list(User $user, array $criteria, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        return Ticket::query()
            ->select(self::COLUMNS)
            ->with(self::RELATIONS)
            ->whereHas('orderItem.order', fn (Builder $orders) => $orders->where('user_id', $user->id))
            ->when($criteria['orderId'], fn (Builder $tickets, int $orderId) => $tickets
                ->whereHas('orderItem', fn (Builder $items) => $items->where('order_id', $orderId)))
            ->when($criteria['status'], fn (Builder $tickets, string $status) => $tickets->where('status', $status))
            ->orderBy(...$sort)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function find(int|string $id): Ticket
    {
        return Ticket::query()->with(self::RELATIONS)->findOrFail($id, self::COLUMNS);
    }

    /**
     * A void ticket no longer lets anyone in, so its PDF is not handed out again.
     *
     * @throws TicketVoid
     * @throws TicketPdfNotReady
     */
    public function pdfPath(Ticket $ticket): string
    {
        if ($ticket->status === TicketStatus::Void) {
            throw new TicketVoid;
        }

        if ($ticket->pdf_path === null) {
            throw new TicketPdfNotReady;
        }

        return $ticket->pdf_path;
    }
}
