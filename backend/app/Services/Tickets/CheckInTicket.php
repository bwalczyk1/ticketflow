<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Events\Tickets\TicketCheckedIn;
use App\Exceptions\Domain\CheckInClosed;
use App\Exceptions\Domain\TicketAlreadyUsed;
use App\Exceptions\Domain\TicketNotFound;
use App\Exceptions\Domain\TicketVoid;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class CheckInTicket
{
    /**
     * The status changes by compare-and-set on VALID, so of two gates scanning the same code at
     * once exactly one wins, and only the winner announces the check-in.
     *
     * @param  Event  $event  with status, starts_at, ends_at and timezone
     *
     * @throws TicketNotFound
     * @throws CheckInClosed
     * @throws TicketAlreadyUsed
     * @throws TicketVoid
     */
    public function handle(Event $event, string $code): Ticket
    {
        $ticket = $this->ticketOfEvent($event, $code);

        CheckInWindow::ensureOpen($event->status, $event->starts_at, $event->ends_at, $event->timezone, now());

        $this->ensureValid($ticket);

        $checkedInAt = now()->toImmutable();

        $updated = Ticket::query()
            ->whereKey($ticket->id)
            ->where('status', TicketStatus::Valid)
            ->update(['status' => TicketStatus::Used, 'checked_in_at' => $checkedInAt]);

        if ($updated === 0) {
            // Another gate won between the read and the update, so its result is the one to show.
            $this->ensureValid(Ticket::query()->whereKey($ticket->id)->firstOrFail(['id', 'status', 'checked_in_at']));
        }

        $ticket->forceFill(['status' => TicketStatus::Used, 'checked_in_at' => $checkedInAt])->syncOriginal();

        TicketCheckedIn::dispatch($ticket->id);

        return $ticket;
    }

    /**
     * @throws TicketNotFound
     */
    private function ticketOfEvent(Event $event, string $code): Ticket
    {
        $ticket = Ticket::query()
            ->with(['orderItem:id,order_id,ticket_type_id', 'orderItem.ticketType:id,name', 'orderItem.order:id,user_id', 'orderItem.order.user:id,name'])
            ->where('code', $code)
            ->whereHas('orderItem.order', fn (Builder $orders) => $orders->where('event_id', $event->id))
            ->first(['id', 'order_item_id', 'code', 'status', 'checked_in_at']);

        if ($ticket === null) {
            throw new TicketNotFound;
        }

        return $ticket;
    }

    /**
     * @throws TicketAlreadyUsed
     * @throws TicketVoid
     */
    private function ensureValid(Ticket $ticket): void
    {
        match ($ticket->status) {
            TicketStatus::Used => throw new TicketAlreadyUsed($ticket->checked_in_at),
            TicketStatus::Void => throw new TicketVoid,
            TicketStatus::Valid => null,
        };
    }
}
