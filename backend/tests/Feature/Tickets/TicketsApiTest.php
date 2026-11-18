<?php

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');

    $this->attendee = User::factory()->create();
    $this->order = TicketFixtures::paidOrder([2], $this->attendee);
    $this->tickets = TicketFixtures::issueTickets($this->order);
});

function readyTicket(Ticket $ticket): Ticket
{
    $ticket->update(['pdf_path' => "tickets/{$ticket->code}.pdf"]);

    Storage::disk('local')->put($ticket->pdf_path, '%PDF-1.7 ticket');

    return $ticket;
}

it('asks for a session before listing tickets', function () {
    $this->getJson('/api/tickets')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('lets only attendees list tickets', function () {
    $this->actingAs(User::factory()->organizer()->create())
        ->getJson('/api/tickets')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('lists only own tickets in the list format', function () {
    TicketFixtures::issueTickets(TicketFixtures::paidOrder([3]));

    $this->actingAs($this->attendee)
        ->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 2, 'totalPages' => 1]);
});

it('shows the code, the status and the event of a ticket', function () {
    $event = Event::query()->find($this->order->event_id, ['id', 'title']);
    $ticketType = TicketType::query()->find($this->order->items()->value('ticket_type_id'), ['id', 'name']);
    $ticket = readyTicket($this->tickets->first());

    $this->actingAs($this->attendee)
        ->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonPath('data.0.id', $ticket->id)
        ->assertJsonPath('data.0.code', $ticket->code)
        ->assertJsonPath('data.0.status', 'VALID')
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.0.checkedInAt', null)
        ->assertJsonPath('data.0.pdfReady', true)
        ->assertJsonPath('data.0.orderId', $this->order->id)
        ->assertJsonPath('data.0.ticketType', ['id' => $ticketType->id, 'name' => $ticketType->name])
        ->assertJsonPath('data.0.event.id', $event->id)
        ->assertJsonPath('data.0.event.title', $event->title)
        ->assertJsonStructure(['data' => [['event' => ['venue', 'city', 'timezone', 'startsAt']]]])
        ->assertJsonPath('data.1.pdfReady', false);
});

it('filters by order and by status', function () {
    $other = TicketFixtures::paidOrder([1], $this->attendee);

    TicketFixtures::issueTickets($other);

    $this->tickets->first()->update(['status' => TicketStatus::Void]);

    $this->actingAs($this->attendee);

    expect($this->getJson("/api/tickets?orderId={$other->id}")->json('data.*.orderId'))->toBe([$other->id]);
    expect($this->getJson('/api/tickets?status=VOID')->json('data.*.id'))->toBe([$this->tickets->first()->id]);
});

it('rejects an unknown filter value or sort with 400', function (string $query, string $field) {
    $this->actingAs($this->attendee)
        ->getJson("/api/tickets?{$query}")
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    ['status=LOST', 'status'],
    ['orderId=abc', 'orderId'],
    ['sort=code', 'sort'],
]);

it('lists tickets with a constant number of queries', function () {
    $this->actingAs($this->attendee);

    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/tickets')->assertOk();

        return count(DB::getQueryLog());
    };

    $forOneOrder = $countQueries();

    TicketFixtures::issueTickets(TicketFixtures::paidOrder([2, 2], $this->attendee));

    expect($countQueries())->toBe($forOneOrder);
});

it('downloads the PDF of an own ticket', function () {
    $ticket = readyTicket($this->tickets->first());

    $response = $this->actingAs($this->attendee)->get("/api/tickets/{$ticket->id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain("bilet-{$ticket->code}.pdf")
        ->and($response->streamedContent())->toBe('%PDF-1.7 ticket');
});

it('answers 404 for a ticket of another user', function () {
    $foreign = readyTicket(TicketFixtures::issueTickets(TicketFixtures::paidOrder())->first());

    $this->actingAs($this->attendee)
        ->getJson("/api/tickets/{$foreign->id}/pdf")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('answers 404 for a ticket that cannot exist', function (string $id) {
    $this->actingAs($this->attendee)
        ->getJson("/api/tickets/{$id}/pdf")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
})->with(['999999', 'abc']);

it('refuses the PDF of a void ticket', function () {
    $ticket = readyTicket($this->tickets->first());

    $ticket->update(['status' => TicketStatus::Void]);

    $this->actingAs($this->attendee)
        ->getJson("/api/tickets/{$ticket->id}/pdf")
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_VOID');
});

it('answers 409 while the PDF is still being generated', function () {
    $this->actingAs($this->attendee)
        ->getJson("/api/tickets/{$this->tickets->first()->id}/pdf")
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_PDF_NOT_READY');
});

it('lets only attendees download a PDF', function () {
    $ticket = readyTicket($this->tickets->first());

    $this->getJson("/api/tickets/{$ticket->id}/pdf")->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create())
        ->getJson("/api/tickets/{$ticket->id}/pdf")
        ->assertForbidden();
});
