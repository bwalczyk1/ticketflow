<?php

namespace App\Http\Controllers\Tickets;

use App\Exceptions\Domain\TicketPdfNotReady;
use App\Exceptions\Domain\TicketVoid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ListTicketsRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\AttendeeTickets;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(private AttendeeTickets $tickets) {}

    /**
     * List own tickets.
     *
     * Tickets appear a moment after the payment, because they are issued in the background.
     */
    public function index(ListTicketsRequest $request): array
    {
        $tickets = $this->tickets->list(
            $request->user(),
            $request->criteria(),
            $request->sortColumn(),
            $request->limit(),
            $request->page(),
        );

        return Pagination::response($tickets, TicketResource::class);
    }

    /**
     * Download the PDF of an own ticket.
     *
     * Another user's ticket answers 404, like an order.
     *
     * @throws ModelNotFoundException
     * @throws TicketVoid
     * @throws TicketPdfNotReady
     */
    public function pdf(string $id): StreamedResponse
    {
        $ticket = $this->tickets->find($id);

        if (Gate::denies('view', $ticket)) {
            throw (new ModelNotFoundException)->setModel(Ticket::class, [$id]);
        }

        return Storage::disk('local')->download(
            $this->tickets->pdfPath($ticket),
            "bilet-{$ticket->code}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
