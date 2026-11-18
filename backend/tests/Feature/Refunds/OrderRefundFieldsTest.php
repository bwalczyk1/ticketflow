<?php

use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;

beforeEach(function () {
    $this->attendee = User::factory()->create();
    $this->event = Event::factory()->create(['starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);
    $this->actingAs($this->attendee);
});

function refundFieldsOrder(User $user, Event $event, string $state): Order
{
    $order = Order::factory()->{$state}()->for($user)->create(['event_id' => $event->id, 'total' => 12000]);

    OrderItem::factory()->for($order)->create();

    return $order;
}

it('shows the refund deadline of a paid order in UTC', function () {
    $order = refundFieldsOrder($this->attendee, $this->event, 'paid');

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('refundableUntil', '2026-11-29T18:00:00Z')
        ->assertJsonPath('refund', null)
        ->assertJsonPath('correctionInvoice', null);
});

it('has no refund deadline outside the paid status', function (string $state) {
    $order = refundFieldsOrder($this->attendee, $this->event, $state);

    $this->getJson("/api/orders/{$order->id}")->assertJsonPath('refundableUntil', null);
})->with(['refunded', 'cancelled', 'expired', 'failed']);

it('shows the refund and the correction invoice of a refunded order in the list and the details', function () {
    $order = refundFieldsOrder($this->attendee, $this->event, 'refunded');
    $refund = Refund::factory()->for($order)->succeeded()->create(['amount' => 12000, 'reason' => null, 'created_at' => '2026-11-14 10:00:00']);
    $original = Invoice::factory()->for($order)->create(['year' => 2026, 'number' => 7, 'pdf_path' => 'invoices/2026/invoice-7.pdf']);

    Invoice::factory()->correction($original, $refund)->create([
        'year' => 2026,
        'number' => 3,
        'pdf_path' => 'invoices/2026/correction-3.pdf',
        'created_at' => '2026-11-14 10:05:00',
    ]);

    $expected = [
        'refund' => ['status' => 'SUCCEEDED', 'amount' => 12000, 'reason' => null, 'createdAt' => '2026-11-14T10:00:00Z'],
        'correctionInvoice' => ['number' => '3/2026', 'issuedAt' => '2026-11-14T10:05:00Z'],
        'invoice.number' => '7/2026',
    ];

    foreach (["/api/orders/{$order->id}" => '', '/api/orders' => 'data.0.'] as $path => $prefix) {
        $response = $this->getJson($path)->assertOk();

        foreach ($expected as $key => $value) {
            $response->assertJsonPath($prefix.$key, $value);
        }
    }
});

it('hides a correction invoice whose PDF is not ready', function () {
    $order = refundFieldsOrder($this->attendee, $this->event, 'refunded');
    $refund = Refund::factory()->for($order)->create(['status' => RefundStatus::Pending]);
    $original = Invoice::factory()->for($order)->create();

    Invoice::factory()->correction($original, $refund)->create(['pdf_path' => null]);

    $this->getJson("/api/orders/{$order->id}")
        ->assertJsonPath('correctionInvoice', null)
        ->assertJsonPath('refund.status', 'PENDING');
});
