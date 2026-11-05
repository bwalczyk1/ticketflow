<?php

namespace App\Services\Documents;

use App\Contracts\TicketPdfGenerator;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DompdfTicketPdfGenerator implements TicketPdfGenerator
{
    public function generate(Ticket $ticket): string
    {
        $path = DocumentPaths::ticket($ticket->code);
        $pdf = Pdf::loadHTML($this->html($ticket))->setPaper('a5')->output();

        // A half-written file must never sit under the final name, so a retry can safely overwrite it.
        $disk = Storage::disk('local');
        $temporaryPath = $path.'.'.Str::random(8).'.tmp';

        $disk->put($temporaryPath, $pdf);
        $disk->move($temporaryPath, $path);

        return $path;
    }

    public function html(Ticket $ticket): string
    {
        $ticket->loadMissing('orderItem.ticketType', 'orderItem.order.event', 'orderItem.order.user');

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
