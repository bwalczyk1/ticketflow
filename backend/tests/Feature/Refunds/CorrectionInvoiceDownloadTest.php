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
    $this->order = Order::factory()->refunded()->for($this->attendee)->create();
    $this->refund = Refund::factory()->for($this->order)->create();
    $this->original = Invoice::factory()->for($this->order)->create(['year' => 2026, 'number' => 7, 'pdf_path' => 'invoices/2026/invoice-7.pdf']);

    OrderItem::factory()->for($this->order)->create();

    $this->path = "/api/orders/{$this->order->id}/correction-invoice";
    $this->actingAs($this->attendee);
});

function issuedCorrection(Invoice $original, Refund $refund, ?string $pdfPath = 'invoices/2026/correction-3.pdf'): Invoice
{
    $correction = Invoice::factory()->correction($original, $refund)->create(['year' => 2026, 'number' => 3, 'pdf_path' => $pdfPath]);

    if ($pdfPath !== null) {
        Storage::disk('local')->put($pdfPath, '%PDF-1.7 correction');
    }

    return $correction;
}

it('downloads the correction invoice of an own refunded order', function () {
    issuedCorrection($this->original, $this->refund);

    $response = $this->get($this->path)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="invoice-correction-3-2026.pdf"')
        ->and($response->streamedContent())->toBe('%PDF-1.7 correction');
});

it('answers 404 INVOICE_NOT_FOUND until the correction PDF exists', function (bool $rowWithoutPdf) {
    if ($rowWithoutPdf) {
        issuedCorrection($this->original, $this->refund, null);
    }

    $this->getJson($this->path)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'INVOICE_NOT_FOUND');
})->with([
    'no correction yet' => false,
    'PDF still being generated' => true,
]);

it('answers 404 NOT_FOUND for a correction of another user', function () {
    issuedCorrection($this->original, $this->refund);

    $this->actingAs(User::factory()->create());

    $this->getJson($this->path)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('is closed to guests and other roles', function () {
    auth()->forgetGuards();
    $this->getJson($this->path)->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    $this->getJson($this->path)->assertForbidden();
});
