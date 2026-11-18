<?php

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Documents\IssueCorrectionInvoice;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Documents\DompdfInvoicePdfGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 12:00:00');

    $this->order = TicketFixtures::paidOrder([2]);

    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Refunded]);

    $this->refund = Refund::factory()->for($this->order)->create(['amount' => $this->order->total, 'reason' => 'Nie mogę przyjść']);
    $this->original = Invoice::factory()->for($this->order)->create(['year' => 2026, 'number' => 7, 'pdf_path' => 'invoices/2026/invoice-7.pdf']);
    $this->event = new OrderRefunded($this->order->id, $this->refund->id);
});

function correctionsOf(Order $order): Collection
{
    return Invoice::query()->where('order_id', $order->id)->where('type', InvoiceType::Correction)->get();
}

it('issues a correction that points to the original invoice, with its own numbering and PDF', function () {
    InvoiceSequence::factory()->create(['year' => 2026, 'type' => InvoiceType::Invoice, 'last_number' => 40]);

    app(IssueCorrectionInvoice::class)->handle($this->event);

    expect(correctionsOf($this->order)->sole())
        ->corrects_id->toBe($this->original->id)
        ->refund_id->toBe($this->refund->id)
        ->year->toBe(2026)
        ->number->toBe(1)
        ->pdf_path->toBe('invoices/2026/correction-1.pdf');

    Storage::disk('local')->assertExists('invoices/2026/correction-1.pdf');
});

it('keeps one correction and burns no number when it runs twice', function () {
    app(IssueCorrectionInvoice::class)->handle($this->event);
    app(IssueCorrectionInvoice::class)->handle($this->event);

    expect(correctionsOf($this->order))->toHaveCount(1)
        ->and(InvoiceSequence::query()->where('year', 2026)->where('type', InvoiceType::Correction)->value('last_number'))->toBe(1);
});

it('waits for the original invoice before issuing the correction', function () {
    $this->original->delete();

    $listener = app(IssueCorrectionInvoice::class)->withFakeQueueInteractions();

    $listener->handle($this->event);

    $listener->assertReleased(delay: 30);

    expect(correctionsOf($this->order))->toHaveCount(0)
        ->and(InvoiceSequence::query()->where('type', InvoiceType::Correction)->exists())->toBeFalse();
});

it('does nothing for an order that is not refunded', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Paid]);

    app(IssueCorrectionInvoice::class)->handle($this->event);

    expect(correctionsOf($this->order))->toHaveCount(0);
});

it('prints negative amounts and the reason on the correction', function () {
    app(IssueCorrectionInvoice::class)->handle($this->event);

    $html = app(DompdfInvoicePdfGenerator::class)->html(correctionsOf($this->order)->sole());

    expect($html)->toContain('Faktura korygująca nr 1/2026', 'do faktury nr 7/2026', 'Powód: Nie mogę przyjść')
        ->toContain('-2', '-100,00 zł');
});

it('prints a correction without a reason', function () {
    $this->refund->update(['reason' => null]);

    app(IssueCorrectionInvoice::class)->handle($this->event);

    $html = app(DompdfInvoicePdfGenerator::class)->html(correctionsOf($this->order)->sole());

    expect($html)->toContain('Faktura korygująca')->not->toContain('Powód:');
});
