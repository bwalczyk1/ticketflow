<?php

use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Events\Tickets\TicketCheckedIn;
use App\Models\Event as EventModel;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    // 19:00 in Warsaw on 20 November, checked in an hour before the doors open.
    $this->travelTo('2026-11-20 17:00:00');

    $this->organizer = User::factory()->organizer()->create();
    $this->attendee = User::factory()->create(['name' => 'Anna Nowak']);

    $this->event = EventModel::factory()->for($this->organizer, 'organizer')->create([
        'starts_at' => '2026-11-20 18:00:00',
        'ends_at' => '2026-11-20 21:00:00',
    ]);

    $this->order = TicketFixtures::paidOrder([2], $this->attendee, $this->event);
    $this->ticket = TicketFixtures::issueTickets($this->order)->first();
});

function checkIn(EventModel $event, string $code)
{
    return test()->postJson("/api/organizer/events/{$event->id}/check-in", ['code' => $code]);
}

it('checks a valid ticket in and announces it', function () {
    Event::fake([TicketCheckedIn::class]);

    $ticketTypeName = $this->order->items()->first()->ticketType->name;

    $this->actingAs($this->organizer);

    checkIn($this->event, $this->ticket->code)
        ->assertOk()
        ->assertExactJson(['ticket' => [
            'id' => $this->ticket->id,
            'code' => $this->ticket->code,
            'status' => 'USED',
            'checkedInAt' => '2026-11-20T17:00:00Z',
            'ticketTypeName' => $ticketTypeName,
            'holderName' => 'Anna Nowak',
        ]]);

    expect($this->ticket->fresh())
        ->status->toBe(TicketStatus::Used)
        ->checked_in_at->toDateTimeString()->toBe('2026-11-20 17:00:00');

    Event::assertDispatched(TicketCheckedIn::class, fn (TicketCheckedIn $event) => $event->ticketId === $this->ticket->id);
});

it('refuses the second check-in with the time of the first one', function () {
    Event::fake([TicketCheckedIn::class]);

    $this->actingAs($this->organizer);

    checkIn($this->event, $this->ticket->code)->assertOk();

    $this->travel(5)->minutes();

    checkIn($this->event, $this->ticket->code)
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_ALREADY_USED')
        ->assertJsonPath('error.details', [['field' => 'checkedInAt', 'message' => '2026-11-20T17:00:00Z']]);

    expect($this->ticket->fresh()->checked_in_at->toDateTimeString())->toBe('2026-11-20 17:00:00');

    Event::assertDispatchedTimes(TicketCheckedIn::class, 1);
});

it('refuses a void ticket', function () {
    Event::fake([TicketCheckedIn::class]);

    $this->ticket->update(['status' => TicketStatus::Void]);

    $this->actingAs($this->organizer);

    checkIn($this->event, $this->ticket->code)
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_VOID');

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::Void);

    Event::assertNotDispatched(TicketCheckedIn::class);
});

it('answers TICKET_NOT_FOUND for an unknown code or a ticket of another event', function (Closure $code) {
    $this->actingAs($this->organizer);

    checkIn($this->event, $code())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'TICKET_NOT_FOUND');
})->with([
    'unknown code' => [fn () => '00000000-0000-4000-8000-000000000000'],
    'not a code at all' => [fn () => 'hello'],
    'ticket of another own event' => [function () {
        $other = EventModel::factory()->for(test()->organizer, 'organizer')->create([
            'starts_at' => '2026-11-20 18:00:00',
            'ends_at' => '2026-11-20 21:00:00',
        ]);

        return TicketFixtures::issueTickets(TicketFixtures::paidOrder([1], event: $other))->first()->code;
    }],
]);

it('opens check-in only on the event day in the event time zone', function (string $now, int $status) {
    $this->travelTo($now);

    $this->actingAs($this->organizer);

    $response = checkIn($this->event, $this->ticket->code)->assertStatus($status);

    if ($status === 409) {
        $response->assertJsonPath('error.code', 'CHECK_IN_CLOSED');
    }
})->with([
    'the evening before' => ['2026-11-19 22:59:59', 409],
    'local midnight' => ['2026-11-19 23:00:00', 200],
    'the next day' => ['2026-11-20 23:00:00', 409],
]);

it('closes check-in of a cancelled event', function () {
    $this->event->forceFill(['status' => EventStatus::Cancelled])->save();

    $this->actingAs($this->organizer);

    checkIn($this->event, $this->ticket->code)
        ->assertConflict()
        ->assertJsonPath('error.code', 'CHECK_IN_CLOSED');
});

it('checks in the order 401, 403 role, 400, 404, 403 ownership, 404 ticket, 409', function () {
    $foreign = EventModel::factory()->create();

    checkIn($this->event, $this->ticket->code)->assertUnauthorized();

    $this->actingAs($this->attendee);
    checkIn($this->event, $this->ticket->code)->assertForbidden();

    $this->actingAs($this->organizer);
    $this->postJson('/api/organizer/events/999999/check-in', [])->assertStatus(400);
    $this->postJson('/api/organizer/events/999999/check-in', ['code' => $this->ticket->code, 'ticketId' => 1])->assertStatus(400);
    $this->postJson('/api/organizer/events/999999/check-in', ['code' => $this->ticket->code])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    checkIn($foreign, $this->ticket->code)->assertForbidden();
    checkIn($this->event, '00000000-0000-4000-8000-000000000000')->assertNotFound();

    $this->ticket->forceFill(['status' => TicketStatus::Used, 'checked_in_at' => now()])->save();

    checkIn($this->event, $this->ticket->code)->assertConflict();
});
