<?php

namespace App\Listeners\Documents;

use App\Contracts\InvoicePdfGenerator;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Documents\InvoiceNumberAllocator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IssueCorrectionInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Seconds to wait for IssueInvoice when the refund overtook it.
     */
    private const WAIT_FOR_INVOICE = 30;

    private const COLUMNS = ['id', 'order_id', 'refund_id', 'corrects_id', 'type', 'year', 'number', 'pdf_path'];

    /**
     * More than the documents queue default, because every wait for the original invoice counts as an attempt.
     */
    public int $tries = 10;

    public function __construct(
        private InvoiceNumberAllocator $allocator,
        private InvoicePdfGenerator $pdfs,
    ) {}

    public function viaQueue(): string
    {
        return QueueName::Documents->value;
    }

    /**
     * Works like IssueInvoice: the correction is saved before its PDF, so a retry after a failed
     * PDF reuses its number.
     */
    public function handle(OrderRefunded $event): void
    {
        if (Order::query()->whereKey($event->orderId)->value('status') !== OrderStatus::Refunded) {
            return;
        }

        $original = Invoice::query()
            ->where('order_id', $event->orderId)
            ->where('type', InvoiceType::Invoice)
            ->first(['id']);

        if ($original === null) {
            $this->release(self::WAIT_FOR_INVOICE);

            return;
        }

        $correction = $this->existingCorrection($event->refundId) ?? $this->createCorrection($event, $original);

        if ($correction->pdf_path !== null) {
            return;
        }

        $correction->update(['pdf_path' => $this->pdfs->generate($correction)]);
    }

    public function failed(OrderRefunded $event, Throwable $exception): void
    {
        Log::error('Issuing a correction invoice failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }

    private function existingCorrection(int $refundId): ?Invoice
    {
        return Invoice::query()
            ->where('refund_id', $refundId)
            ->where('type', InvoiceType::Correction)
            ->first(self::COLUMNS);
    }

    /**
     * The number comes from the CORRECTION sequence, only after the check and in the transaction
     * that saves the correction, so a retry or a failed save leaves no gap.
     */
    private function createCorrection(OrderRefunded $event, Invoice $original): Invoice
    {
        return DB::transaction(function () use ($event, $original) {
            // The locking read stays first, so the check below sees a correction committed while waiting.
            Order::query()->whereKey($event->orderId)->lockForUpdate()->first(['id']);

            $correction = $this->existingCorrection($event->refundId);

            if ($correction !== null) {
                return $correction;
            }

            ['year' => $year, 'number' => $number] = $this->allocator->next(InvoiceType::Correction, now());

            return Invoice::query()->create([
                'order_id' => $event->orderId,
                'refund_id' => $event->refundId,
                'corrects_id' => $original->id,
                'type' => InvoiceType::Correction,
                'year' => $year,
                'number' => $number,
                'pdf_path' => null,
            ]);
        });
    }
}
