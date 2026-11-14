<?php

use App\Models\Event as EventModel;
use App\Services\Events\SendEventReminders;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Tickets\TicketFixtures;
use Tests\Integration\Orders\Interleave;

it('lets only one of two overlapping runs send the reminders of an event', function () {
    Queue::fake();
    $this->travelTo('2026-11-19 18:00:00');

    $event = EventModel::factory()->create(['starts_at' => '2026-11-20 18:30:00', 'ends_at' => '2026-11-20 21:00:00']);
    TicketFixtures::paidOrder([1], event: $event);
    $other = secondConnection();

    // Another run claims the event after this one listed it and before it claimed it.
    Interleave::before('update `events`', function () use ($other, $event) {
        $other->table('events')->where('id', $event->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
    });

    expect(app(SendEventReminders::class)->handle())->toBe(0);

    Queue::assertNothingPushed();
});
