<?php

namespace App\Services\Documents;

use App\Contracts\InvoicePdfGenerator;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DompdfInvoicePdfGenerator implements InvoicePdfGenerator
{
    public function generate(Invoice $invoice): string
    {
        $path = DocumentPaths::invoice($invoice->year, $invoice->type, $invoice->number);
        $pdf = Pdf::loadHTML($this->html($invoice))->setPaper('a4')->setOption('enable_font_subsetting', true)->output();

        // A half-written file must never sit under the final name, so a retry can safely overwrite it.
        $disk = Storage::disk('local');
        $temporaryPath = $path.'.'.Str::random(8).'.tmp';

        $disk->put($temporaryPath, $pdf);
        $disk->move($temporaryPath, $path);

        return $path;
    }

    public function html(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'order:id,user_id,event_id,total',
            'order.user:id,name,email',
            'order.event:id,title',
            'order.items:id,order_id,ticket_type_id,quantity,unit_price',
            'order.items.ticketType:id,name',
            'refund:id,amount,reason',
            'corrects:id,number,year',
        ]);

        $isCorrection = $invoice->type === InvoiceType::Correction;

        return view('documents.invoice', [
            'invoice' => $invoice,
            'isCorrection' => $isCorrection,
            'order' => $invoice->order,
            'buyer' => $invoice->order->user,
            'items' => $invoice->order->items,
            'total' => $isCorrection ? -$invoice->refund->amount : $invoice->order->total,
        ])->render();
    }
}
