<?php

namespace App\Listeners\Documents;

use App\Contracts\InvoicePdfGenerator;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Documents\InvoiceNumberAllocator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IssueInvoice implements ShouldQueue
{
    /**
     * A refund can overtake this job, and the correction invoice still needs the original.
     */
    private const INVOICED_STATUSES = [OrderStatus::Paid, OrderStatus::Refunded];

    private const COLUMNS = ['id', 'order_id', 'refund_id', 'corrects_id', 'type', 'year', 'number', 'pdf_path'];

    public function __construct(
        private InvoiceNumberAllocator $allocator,
        private InvoicePdfGenerator $pdfs,
    ) {}

    public function viaQueue(): string
    {
        return QueueName::Documents->value;
    }

    /**
     * The invoice is saved before its PDF, so a retry after a failed PDF reuses the number it got.
     */
    public function handle(OrderPaid $event): void
    {
        $invoice = $this->existingInvoice($event->orderId) ?? $this->createInvoice($event->orderId);

        if ($invoice === null || $invoice->pdf_path !== null) {
            return;
        }

        $invoice->update(['pdf_path' => $this->pdfs->generate($invoice)]);
    }

    public function failed(OrderPaid $event, Throwable $exception): void
    {
        Log::error('Issuing an invoice failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }

    private function existingInvoice(int $orderId): ?Invoice
    {
        return Invoice::query()
            ->where('order_id', $orderId)
            ->where('type', InvoiceType::Invoice)
            ->first(self::COLUMNS);
    }

    /**
     * The number is taken only after the check, in the transaction that saves the invoice, so a
     * failed save gives it back and no year gets a gap. The order lock makes a parallel run wait
     * and then find the invoice; UNIQUE (order_id, type, refund_key) stays as the last defence.
     */
    private function createInvoice(int $orderId): ?Invoice
    {
        return DB::transaction(function () use ($orderId) {
            // The locking read stays first, so the check below sees invoices committed while waiting.
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first(['id', 'status']);

            if ($order === null || ! in_array($order->status, self::INVOICED_STATUSES, true)) {
                return null;
            }

            $invoice = $this->existingInvoice($orderId);

            if ($invoice !== null) {
                return $invoice;
            }

            ['year' => $year, 'number' => $number] = $this->allocator->next(InvoiceType::Invoice, now());

            return Invoice::query()->create([
                'order_id' => $orderId,
                'refund_id' => null,
                'corrects_id' => null,
                'type' => InvoiceType::Invoice,
                'year' => $year,
                'number' => $number,
                'pdf_path' => null,
            ]);
        });
    }
}
