<?php

use App\Enums\TicketStatus;
use App\Events\Tickets\TicketCheckedIn;
use App\Exceptions\Domain\TicketAlreadyUsed;
use App\Models\Event as EventModel;
use App\Models\Ticket;
use App\Services\Tickets\CheckInTicket;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Tickets\TicketFixtures;
use Tests\Integration\Orders\Interleave;

it('lets only one of two parallel scans of the same ticket through', function () {
    Event::fake([TicketCheckedIn::class]);
    $this->travelTo('2026-11-20 17:00:00');

    $event = EventModel::factory()->create(['starts_at' => '2026-11-20 18:00:00', 'ends_at' => '2026-11-20 21:00:00']);
    $ticket = TicketFixtures::issueTickets(TicketFixtures::paidOrder([1], event: $event))->first();
    $other = secondConnection();

    // The other gate scans the same code after this request has read the ticket as VALID.
    Interleave::before('update `tickets`', function () use ($other, $ticket) {
        $other->table('tickets')->where('id', $ticket->id)->where('status', 'VALID')->update([
            'status' => 'USED',
            'checked_in_at' => '2026-11-20 16:59:59',
        ]);
    });

    expect(fn () => app(CheckInTicket::class)->handle($event, $ticket->code))
        ->toThrow(TicketAlreadyUsed::class);

    expect(Ticket::query()->where('status', TicketStatus::Used)->count())->toBe(1)
        ->and($ticket->fresh()->checked_in_at->toDateTimeString())->toBe('2026-11-20 16:59:59');

    Event::assertNotDispatched(TicketCheckedIn::class);
});
