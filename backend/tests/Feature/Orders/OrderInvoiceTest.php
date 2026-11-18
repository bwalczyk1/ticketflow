<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->attendee = User::factory()->create();
    $this->order = Order::factory()->paid()->for($this->attendee)->create();

    OrderItem::factory()->for($this->order)->create();

    $this->actingAs($this->attendee);
});

function issuedInvoice(Order $order, array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->for($order)->create([
        'year' => 2026,
        'number' => 7,
        'pdf_path' => 'invoices/2026/invoice-7.pdf',
        'created_at' => '2026-11-14 10:15:00',
        ...$attributes,
    ]);

    Storage::disk('local')->put($invoice->pdf_path, '%PDF-1.7 invoice');

    return $invoice;
}

it('downloads the invoice of an own order', function () {
    issuedInvoice($this->order);

    $response = $this->get("/api/orders/{$this->order->id}/invoice")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="invoice-7-2026.pdf"')
        ->and($response->streamedContent())->toBe('%PDF-1.7 invoice');
});

it('answers 404 INVOICE_NOT_FOUND before the invoice is issued', function (bool $rowWithoutPdf) {
    if ($rowWithoutPdf) {
        Invoice::factory()->for($this->order)->create(['pdf_path' => null]);
    }

    $this->getJson("/api/orders/{$this->order->id}/invoice")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'INVOICE_NOT_FOUND');
})->with([
    'no invoice yet' => false,
    'PDF still being generated' => true,
]);

it('serves the original invoice and not a correction', function () {
    $original = issuedInvoice($this->order);
    $refund = Refund::factory()->for($this->order)->create();

    Invoice::factory()->correction($original, $refund)->create(['year' => 2026, 'number' => 1, 'pdf_path' => 'invoices/2026/correction-1.pdf']);

    $response = $this->get("/api/orders/{$this->order->id}/invoice")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('invoice-7-2026.pdf');
});

it('answers 404 NOT_FOUND for an invoice of another user', function () {
    $foreign = Order::factory()->paid()->create();

    issuedInvoice($foreign);

    $this->getJson("/api/orders/{$foreign->id}/invoice")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('answers 404 for an order id that cannot exist', function (string $id) {
    $this->getJson("/api/orders/{$id}/invoice")->assertNotFound();
})->with(['999999', 'abc', '99999999999999999999']);

it('checks the invoice download in the order 401, 403 role, 404', function () {
    issuedInvoice($this->order);

    auth()->forgetGuards();

    $this->getJson("/api/orders/{$this->order->id}/invoice")->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->getJson("/api/orders/{$this->order->id}/invoice")->assertForbidden();

    $this->actingAs($this->attendee);
    $this->getJson('/api/orders/999999/invoice')->assertNotFound();
});

it('shows the issued invoice in the order detail and list', function () {
    issuedInvoice($this->order);

    $expected = ['number' => '7/2026', 'issuedAt' => '2026-11-14T10:15:00Z'];

    $this->getJson("/api/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('invoice', $expected);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('data.0.invoice', $expected);
});

it('shows a null invoice until its PDF exists', function () {
    expect($this->getJson("/api/orders/{$this->order->id}")->json())->toHaveKey('invoice', null);

    Invoice::factory()->for($this->order)->create(['pdf_path' => null]);

    expect($this->getJson("/api/orders/{$this->order->id}")->json())->toHaveKey('invoice', null);
});
