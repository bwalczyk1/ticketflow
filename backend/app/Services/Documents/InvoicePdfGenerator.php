<?php

namespace App\Services\Documents;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfGenerator
{
    /**
     * Returns the storage path invoices/{year}/{type}-{number}.pdf.
     */
    public function generate(Invoice $invoice): string
    {
        $pdf = Pdf::loadHTML($this->html($invoice))->setPaper('a4')->setOption('enable_font_subsetting', true)->output();
        $type = strtolower($invoice->type->value);

        return PdfFile::store("invoices/{$invoice->year}/{$type}-{$invoice->number}.pdf", $pdf);
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
            // A correction returns the whole order, so every line is printed with a minus sign.
            'sign' => $isCorrection ? -1 : 1,
            'total' => $isCorrection ? -$invoice->refund->amount : $invoice->order->total,
        ])->render();
    }
}
