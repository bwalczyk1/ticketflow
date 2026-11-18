<?php

use App\Enums\InvoiceType;
use App\Events\Orders\OrderPaid;
use App\Listeners\Documents\IssueInvoice;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;
use Tests\Integration\Orders\Interleave;

it('finds the invoice of a parallel run that committed while it waited for the order', function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 12:00:00');

    $order = TicketFixtures::paidOrder();
    $other = secondConnection();

    // The other worker issues invoice number 1 between this run's first check and its lock.
    Interleave::before(Interleave::ORDER_LOCK, function () use ($other, $order) {
        $other->table('invoice_sequences')->insert(['year' => 2026, 'type' => InvoiceType::Invoice->value, 'last_number' => 1]);

        $other->table('invoices')->insert([
            'order_id' => $order->id,
            'type' => InvoiceType::Invoice->value,
            'year' => 2026,
            'number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    app(IssueInvoice::class)->handle(new OrderPaid($order->id));

    expect(Invoice::query()->where('order_id', $order->id)->sole())
        ->number->toBe(1)
        ->pdf_path->toBe('invoices/2026/invoice-1.pdf')
        ->and(InvoiceSequence::query()->where('year', 2026)->value('last_number'))->toBe(1);
});
