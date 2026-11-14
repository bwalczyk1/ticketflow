<?php

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Tickets\VoidTickets;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->order = TicketFixtures::paidOrder([2, 1]);
    $this->tickets = TicketFixtures::issueTickets($this->order);

    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Refunded]);

    $this->refund = Refund::factory()->for($this->order)->create();
    $this->event = new OrderRefunded($this->order->id, $this->refund->id);
});

/**
 * @return list<TicketStatus>
 */
function ticketStatusesOf(Order $order): array
{
    return TicketFixtures::ticketsOf($order)->pluck('status')->all();
}

it('voids every ticket of the refunded order and is idempotent', function () {
    app(VoidTickets::class)->handle($this->event);
    app(VoidTickets::class)->handle($this->event);

    expect(ticketStatusesOf($this->order))->toBe([TicketStatus::Void, TicketStatus::Void, TicketStatus::Void]);
});

it('leaves tickets of other orders alone', function () {
    $other = TicketFixtures::paidOrder([1]);

    TicketFixtures::issueTickets($other);

    app(VoidTickets::class)->handle($this->event);

    expect(ticketStatusesOf($other))->toBe([TicketStatus::Valid]);
});

it('keeps a ticket that was already used', function () {
    $this->tickets[0]->update(['status' => TicketStatus::Used, 'checked_in_at' => now()]);

    app(VoidTickets::class)->handle($this->event);

    expect(ticketStatusesOf($this->order))->toBe([TicketStatus::Used, TicketStatus::Void, TicketStatus::Void]);
});

it('does not void tickets of an order that is not refunded', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Paid]);

    app(VoidTickets::class)->handle($this->event);

    expect(ticketStatusesOf($this->order))->toBe([TicketStatus::Valid, TicketStatus::Valid, TicketStatus::Valid]);
});

it('stops handing out the PDF of a voided ticket', function () {
    Storage::fake('local');

    $ticket = $this->tickets[0];

    $ticket->update(['pdf_path' => "tickets/{$ticket->code}.pdf"]);
    Storage::disk('local')->put($ticket->pdf_path, '%PDF-1.7 ticket');

    app(VoidTickets::class)->handle($this->event);

    $this->actingAs($this->order->user)
        ->getJson("/api/tickets/{$ticket->id}/pdf")
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_VOID');
});
