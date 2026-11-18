<?php

namespace App\Services\Documents;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketPdfGenerator
{
    /**
     * Returns the storage path tickets/{code}.pdf.
     */
    public function generate(Ticket $ticket): string
    {
        $pdf = Pdf::loadHTML($this->html($ticket))->setPaper('a5')->setOption('enable_font_subsetting', true)->output();

        return PdfFile::store("tickets/{$ticket->code}.pdf", $pdf);
    }

    public function html(Ticket $ticket): string
    {
        $ticket->loadMissing([
            'orderItem:id,order_id,ticket_type_id',
            'orderItem.ticketType:id,name',
            'orderItem.order:id,user_id,event_id',
            'orderItem.order.event:id,title,venue,city,timezone,starts_at',
            'orderItem.order.user:id,name',
        ]);

        $item = $ticket->orderItem;
        $event = $item->order->event;

        return view('documents.ticket', [
            'ticket' => $ticket,
            'event' => $event,
            'ticketType' => $item->ticketType,
            'holder' => $item->order->user,
            'startsAt' => $event->localStartsAt(),
            'qrCode' => QrCodeImage::dataUri($ticket->code),
        ])->render();
    }
}
