<?php

use App\Enums\WaitlistStatus;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([WaitlistOfferCreated::class]);
    $this->travelTo('2026-11-14 12:00:00');

    $this->pool = TicketType::factory()->create(['quantity' => 10, 'sold' => 8, 'held' => 2]);
    $this->offer = WaitlistEntry::factory()->for($this->pool)->create([
        'quantity' => 2,
        'status' => WaitlistStatus::Offered,
        'offered_until' => '2026-11-14 12:30:00',
    ]);
});

it('expires an offer at the exact second it ends and not before', function (string $now, WaitlistStatus $status) {
    $this->travelTo($now);

    $this->artisan('ticketflow:expire-waitlist-offers')->assertSuccessful();

    expect($this->offer->fresh()->status)->toBe($status);
})->with([
    'one second before' => ['2026-11-14 12:29:59', WaitlistStatus::Offered],
    'at the end' => ['2026-11-14 12:30:00', WaitlistStatus::Expired],
]);

it('offers the seats of an expired offer to the next person in line', function () {
    $next = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 1]);
    $this->travelTo('2026-11-14 12:30:00');

    $this->artisan('ticketflow:expire-waitlist-offers')
        ->expectsOutput('Expired 1 waitlist offers.')
        ->assertSuccessful();

    expect($next->fresh())->status->toBe(WaitlistStatus::Offered)
        ->and($next->fresh()->offered_until->toDateTimeString())->toBe('2026-11-14 13:00:00')
        ->and($this->pool->fresh()->held)->toBe(1);

    Event::assertDispatched(WaitlistOfferCreated::class, fn (WaitlistOfferCreated $event) => $event->waitlistEntryId === $next->id);
});

it('returns the seats to the pool when nobody else waits', function () {
    $this->travelTo('2026-11-14 12:30:00');

    $this->artisan('ticketflow:expire-waitlist-offers')->assertSuccessful();

    expect($this->pool->fresh()->held)->toBe(0);
});

it('gives the seats back only once when it runs twice', function () {
    $other = TicketType::factory()->create(['quantity' => 5, 'held' => 1]);
    $this->travelTo('2026-11-14 12:30:00');

    $this->artisan('ticketflow:expire-waitlist-offers')->assertSuccessful();
    $this->artisan('ticketflow:expire-waitlist-offers')->expectsOutput('Expired 0 waitlist offers.')->assertSuccessful();

    expect($this->pool->fresh()->held)->toBe(0)
        ->and($other->fresh()->held)->toBe(1);
});

it('leaves entries without an open offer alone', function (WaitlistStatus $status) {
    $this->offer->update(['status' => $status]);
    $this->travelTo('2026-11-14 13:00:00');

    $this->artisan('ticketflow:expire-waitlist-offers')->assertSuccessful();

    expect($this->offer->fresh()->status)->toBe($status)
        ->and($this->pool->fresh()->held)->toBe(2);
})->with([WaitlistStatus::Converted, WaitlistStatus::Cancelled]);

it('runs every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'ticketflow:expire-waitlist-offers'));

    expect($event?->expression)->toBe('* * * * *');
});
