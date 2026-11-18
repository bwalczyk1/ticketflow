<?php

use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SentNotification;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function violates(Closure $write): void
{
    expect($write)->toThrow(QueryException::class);
}

it('keeps the ticket pool consistent', function (array $values) {
    $type = TicketType::factory()->create(['quantity' => 5]);

    violates(fn () => DB::table('ticket_types')->where('id', $type->id)->update($values));
})->with([
    'sold over the pool' => [['sold' => 6]],
    'held over the pool' => [['held' => 6]],
    'sold and held over the pool' => [['sold' => 3, 'held' => 3]],
    'negative sold' => [['sold' => -1]],
    'negative held' => [['held' => -1]],
]);

it('accepts a pool filled exactly to the limit', function () {
    $type = TicketType::factory()->create(['quantity' => 5]);

    DB::table('ticket_types')->where('id', $type->id)->update(['sold' => 3, 'held' => 2]);

    expect($type->fresh()->sold)->toBe(3);
});

it('allows one payment per order', function () {
    $payment = Payment::factory()->create();

    violates(fn () => Payment::factory()->for($payment->order)->create());
});

it('allows one refund per order', function () {
    $refund = Refund::factory()->create();

    violates(fn () => Refund::factory()->for($refund->order)->create());
});

it('keeps refund statuses in line with payments and lets the reason be empty', function () {
    $refund = Refund::factory()->create(['reason' => null]);

    DB::table('refunds')->where('id', $refund->id)->update(['status' => 'SUCCEEDED']);

    expect($refund->fresh()->reason)->toBeNull();

    violates(fn () => DB::table('refunds')->where('id', $refund->id)->update(['status' => 'COMPLETED']));
    violates(fn () => DB::table('refunds')->where('id', $refund->id)->update(['reason' => str_repeat('a', 501)]));
});

it('does not repeat a ticket position or code', function () {
    $ticket = Ticket::factory()->create(['position' => 1]);

    violates(fn () => Ticket::factory()->for($ticket->orderItem)->create(['position' => 1]));
    violates(fn () => Ticket::factory()->create(['code' => $ticket->code]));
});

it('does not repeat an invoice number within a year and type', function () {
    $invoice = Invoice::factory()->create(['year' => 2026, 'number' => 1]);

    violates(fn () => Invoice::factory()->create(['year' => 2026, 'number' => 1]));

    expect(Invoice::factory()->create(['year' => 2027, 'number' => 1])->exists)->toBeTrue()
        ->and($invoice->exists)->toBeTrue();
});

it('allows one invoice per order and one correction per refund', function () {
    $invoice = Invoice::factory()->create();

    violates(fn () => Invoice::factory()->for($invoice->order)->create());

    $refund = Refund::factory()->for($invoice->order)->create();

    Invoice::factory()->correction($invoice, $refund)->create();

    violates(fn () => Invoice::factory()->correction($invoice, $refund)->create());
});

it('allows one active waitlist entry per user and pool', function () {
    $entry = WaitlistEntry::factory()->create();

    violates(fn () => WaitlistEntry::factory()->for($entry->user)->for($entry->ticketType)->offered()->create());

    $entry->update(['status' => WaitlistStatus::Expired]);

    $second = WaitlistEntry::factory()->for($entry->user)->for($entry->ticketType)->create();

    $second->update(['status' => WaitlistStatus::Converted]);

    expect(WaitlistEntry::factory()->for($entry->user)->for($entry->ticketType)->create()->exists)->toBeTrue();
});

it('sends a notification key only once', function () {
    SentNotification::factory()->create(['key' => 'order:1:tickets']);

    violates(fn () => SentNotification::factory()->create(['key' => 'order:1:tickets']));
});

it('does not repeat a user email', function () {
    $user = User::factory()->create();

    violates(fn () => User::factory()->create(['email' => $user->email]));
});

it('rejects values outside the enums', function (string $table, Closure $id) {
    violates(fn () => DB::table($table)->where('id', $id())->update([$table === 'users' ? 'role' : 'status' => 'NOPE']));
})->with([
    'users' => ['users', fn () => User::factory()->create()->id],
    'events' => ['events', fn () => Event::factory()->create()->id],
    'orders' => ['orders', fn () => Order::factory()->create()->id],
    'payments' => ['payments', fn () => Payment::factory()->create()->id],
    'tickets' => ['tickets', fn () => Ticket::factory()->create()->id],
    'refunds' => ['refunds', fn () => Refund::factory()->create()->id],
    'waitlist_entries' => ['waitlist_entries', fn () => WaitlistEntry::factory()->create()->id],
]);

it('rejects an event that ends before it starts', function () {
    $event = Event::factory()->create();

    violates(fn () => DB::table('events')->where('id', $event->id)->update(['ends_at' => $event->starts_at->subHour()]));
});

it('rejects an empty order item', function () {
    violates(fn () => OrderItem::factory()->create(['quantity' => 0]));
});

it('protects sales history from deletion', function () {
    $item = OrderItem::factory()->create();

    Ticket::factory()->for($item)->create();

    violates(fn () => $item->order->delete());
    violates(fn () => $item->ticketType->delete());
    violates(fn () => $item->order->user->delete());
    violates(fn () => $item->order->event->delete());
});

it('removes ticket types of an event without sales', function () {
    $type = TicketType::factory()->create();

    $type->event->delete();

    expect(TicketType::query()->whereKey($type->id)->exists())->toBeFalse();
});
