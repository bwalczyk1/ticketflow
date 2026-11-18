<?php

use App\Contracts\InvoicePdfGenerator;
use App\Enums\InvoiceType;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Documents\DompdfInvoicePdfGenerator;
use Illuminate\Support\Facades\Storage;

function invoiceForOrder(array $invoice = []): Invoice
{
    $event = Event::factory()->create(['title' => 'Koncert Żółć']);

    $order = Order::factory()->paid()->create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['name' => 'Zażółć Gęślą', 'email' => 'zazolc@example.com'])->id,
        'total' => 25000,
    ]);

    $vip = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Strefa VIP', 'price' => 15000]);
    $standard = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Płyta', 'price' => 5000]);

    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $vip->id, 'quantity' => 1, 'unit_price' => 15000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $standard->id, 'quantity' => 2, 'unit_price' => 5000]);

    return Invoice::factory()->create(array_merge([
        'order_id' => $order->id,
        'year' => 2026,
        'number' => 7,
    ], $invoice));
}

it('writes a PDF to invoices/{year}/{type}-{number}.pdf and returns the path', function () {
    Storage::fake('local');
    $invoice = invoiceForOrder();

    $path = app(InvoicePdfGenerator::class)->generate($invoice);

    expect($path)->toBe('invoices/2026/invoice-7.pdf');

    Storage::disk('local')->assertExists($path);

    expect(Storage::disk('local')->get($path))->toStartWith('%PDF-');
});

it('shows the order items and the total', function () {
    $invoice = invoiceForOrder();

    $html = app(DompdfInvoicePdfGenerator::class)->html($invoice);

    expect($html)->toContain('Faktura nr 7/2026', 'Zażółć Gęślą', 'zazolc@example.com', 'Koncert Żółć')
        ->toContain('Strefa VIP', 'Płyta', '150,00 zł', '50,00 zł', '100,00 zł', '250,00 zł');
});

it('marks a correction and points to the original invoice', function () {
    $original = invoiceForOrder();
    $refund = Refund::factory()->create(['order_id' => $original->order_id, 'amount' => 25000]);
    $correction = Invoice::factory()->correction($original, $refund)->create(['year' => 2026, 'number' => 2]);

    $html = app(DompdfInvoicePdfGenerator::class)->html($correction);

    expect($correction->type)->toBe(InvoiceType::Correction)
        ->and($html)->toContain('Faktura korygująca nr 2/2026', 'do faktury nr 7/2026', '-250,00 zł');
});
