<?php

use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;

it('links users to their events and orders', function () {
    $organizer = User::factory()->organizer()->create();
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $order = Order::factory()->for($organizer)->for($event)->create();

    expect($organizer->organizedEvents->pluck('id')->all())->toBe([$event->id])
        ->and($organizer->orders->pluck('id')->all())->toBe([$order->id])
        ->and($event->organizer->is($organizer))->toBeTrue();
});

it('links events to ticket types, orders and the sales summary', function () {
    $event = Event::factory()->create();
    $type = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();
    $summary = EventSalesSummary::factory()->for($event)->create();

    expect($event->ticketTypes->pluck('id')->all())->toBe([$type->id])
        ->and($event->orders->pluck('id')->all())->toBe([$order->id])
        ->and($event->salesSummary->is($summary))->toBeTrue()
        ->and($summary->event->is($event))->toBeTrue()
        ->and($type->event->is($event))->toBeTrue();
});

it('links orders to items, tickets, payment, refund and invoices', function () {
    $order = Order::factory()->paid()->create();
    $item = OrderItem::factory()->for($order)->create();
    $ticket = Ticket::factory()->for($item)->create();
    $payment = Payment::factory()->for($order)->succeeded()->create();
    $refund = Refund::factory()->for($order)->create();
    $invoice = Invoice::factory()->for($order)->create();

    expect($order->items->pluck('id')->all())->toBe([$item->id])
        ->and($order->tickets->pluck('id')->all())->toBe([$ticket->id])
        ->and($order->payment->is($payment))->toBeTrue()
        ->and($order->refund->is($refund))->toBeTrue()
        ->and($order->invoices->pluck('id')->all())->toBe([$invoice->id])
        ->and($item->order->is($order))->toBeTrue()
        ->and($item->tickets->pluck('id')->all())->toBe([$ticket->id])
        ->and($ticket->orderItem->is($item))->toBeTrue()
        ->and($payment->order->is($order))->toBeTrue()
        ->and($refund->order->is($order))->toBeTrue();
});

it('keeps an order item on a ticket type of the same event by default', function () {
    $item = OrderItem::factory()->create();

    expect($item->ticketType->event_id)->toBe($item->order->event_id);
});

it('links a correction to the original invoice and the refund', function () {
    $invoice = Invoice::factory()->create();
    $refund = Refund::factory()->for($invoice->order)->create();
    $correction = Invoice::factory()->correction($invoice, $refund)->create();

    expect($correction->corrects->is($invoice))->toBeTrue()
        ->and($correction->refund->is($refund))->toBeTrue()
        ->and($correction->order->is($invoice->order))->toBeTrue();
});

it('links waitlist entries to the user and the ticket type', function () {
    $entry = WaitlistEntry::factory()->create();

    expect($entry->user)->toBeInstanceOf(User::class)
        ->and($entry->ticketType)->toBeInstanceOf(TicketType::class)
        ->and($entry->ticketType->waitlistEntries->pluck('id')->all())->toBe([$entry->id]);
});
