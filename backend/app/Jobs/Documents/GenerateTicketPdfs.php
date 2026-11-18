<?php

namespace App\Jobs\Documents;

use App\Contracts\TicketPdfGenerator;
use App\Enums\QueueName;
use App\Events\Tickets\TicketsGenerated;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatched by IssueTickets once the tickets exist, so the PDFs never race with ticket issuing.
 */
class GenerateTicketPdfs implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue(QueueName::Documents->value);
    }

    /**
     * A ticket with a pdf_path is skipped, so a retry only finishes what is missing. The event goes
     * out on every complete run, because a crash right before it must not lose the e-mail.
     */
    public function handle(TicketPdfGenerator $generator): void
    {
        $tickets = Ticket::query()
            ->whereIn('order_item_id', OrderItem::query()->select('id')->where('order_id', $this->orderId))
            ->orderBy('id')
            ->get(['id', 'order_item_id', 'code', 'pdf_path']);

        if ($tickets->isEmpty()) {
            return;
        }

        foreach ($tickets->whereNull('pdf_path') as $ticket) {
            $ticket->update(['pdf_path' => $generator->generate($ticket)]);
        }

        TicketsGenerated::dispatch($this->orderId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Generating ticket PDFs failed', ['order_id' => $this->orderId, 'error' => $exception::class]);
    }
}
