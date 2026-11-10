<?php

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

function endedEvent(string $endsAt, EventStatus $status = EventStatus::Published): Event
{
    return Event::factory()->create([
        'status' => $status,
        'starts_at' => '2026-11-20 18:00:00',
        'ends_at' => $endsAt,
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-11-20 21:00:00');
});

it('finishes a published event after its end', function () {
    $event = endedEvent('2026-11-20 20:59:59');

    $this->artisan('ticketflow:finish-events')->assertSuccessful();

    expect($event->fresh()->status)->toBe(EventStatus::Finished);
});

it('keeps an event that ends exactly now or later', function (string $endsAt) {
    $event = endedEvent($endsAt);

    $this->artisan('ticketflow:finish-events')->assertSuccessful();

    expect($event->fresh()->status)->toBe(EventStatus::Published);
})->with(['2026-11-20 21:00:00', '2026-11-20 21:00:01']);

it('touches only statuses that may become finished', function (EventStatus $status) {
    $event = endedEvent('2026-11-20 20:00:00', $status);

    $this->artisan('ticketflow:finish-events')->assertSuccessful();

    expect($event->fresh()->status)->toBe($status);
})->with([EventStatus::Draft, EventStatus::Cancelled]);

it('reports how many events it finished', function () {
    endedEvent('2026-11-20 20:00:00');
    endedEvent('2026-11-20 20:30:00');

    $this->artisan('ticketflow:finish-events')
        ->expectsOutput('Finished 2 events.')
        ->assertSuccessful();
});

it('runs on the schedule', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (ScheduledEvent $event) => $event->command);

    expect($commands->filter(fn (?string $command) => str_contains((string) $command, 'ticketflow:finish-events')))
        ->toHaveCount(1);
});
