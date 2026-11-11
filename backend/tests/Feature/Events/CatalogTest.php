<?php

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

it('lists only published events with data and meta', function () {
    $published = Event::factory()->published()->create();
    Event::factory()->draft()->create();
    Event::factory()->cancelled()->create();
    Event::factory()->finished()->create();

    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 1, 'totalPages' => 1]);
});

it('paginates with page and limit', function () {
    Event::factory()->count(5)->create();

    $this->getJson('/api/events?page=2&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta', ['page' => 2, 'limit' => 2, 'total' => 5, 'totalPages' => 3]);
});

it('rejects a limit above 100 with 400', function () {
    $this->getJson('/api/events?limit=101')
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'limit');
});

it('filters by city', function () {
    $krakow = Event::factory()->create(['city' => 'Krakow']);
    Event::factory()->create(['city' => 'Warsaw']);

    $this->getJson('/api/events?city=Krakow')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $krakow->id);
});

it('filters by category', function () {
    $meetup = Event::factory()->create(['category' => 'meetup']);
    Event::factory()->create(['category' => 'concert']);

    $this->getJson('/api/events?category=meetup')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $meetup->id);
});

it('filters by a date range', function () {
    $before = Event::factory()->create(['starts_at' => '2027-03-01 09:59:59', 'ends_at' => '2027-03-01 12:00:00']);
    $first = Event::factory()->create(['starts_at' => '2027-03-01 10:00:00', 'ends_at' => '2027-03-01 12:00:00']);
    $last = Event::factory()->create(['starts_at' => '2027-03-02 10:00:00', 'ends_at' => '2027-03-02 12:00:00']);
    $after = Event::factory()->create(['starts_at' => '2027-03-02 10:00:01', 'ends_at' => '2027-03-02 12:00:00']);

    $response = $this->getJson('/api/events?from=2027-03-01T10:00:00Z&to=2027-03-02T10:00:00Z')->assertOk();

    expect($response->json('data.*.id'))
        ->toBe([$first->id, $last->id])
        ->not->toContain($before->id, $after->id);
});

it('compares date filters as absolute time', function () {
    $event = Event::factory()->create(['starts_at' => '2027-03-01 10:00:00', 'ends_at' => '2027-03-01 12:00:00']);
    $from = urlencode('2027-03-01T11:00:00+01:00');

    $this->getJson("/api/events?from={$from}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $event->id);
});

it('rejects a date filter without a timezone offset', function (string $field) {
    $this->getJson("/api/events?{$field}=2027-03-01T10:00:00")
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', $field);
})->with(['from', 'to']);

it('filters by maximum price', function () {
    $cheap = Event::factory()->create();
    TicketType::factory()->for($cheap)->create(['price' => 5000]);
    TicketType::factory()->for($cheap)->create(['price' => 30000]);
    $expensive = Event::factory()->create();
    TicketType::factory()->for($expensive)->create(['price' => 5001]);

    $this->getJson('/api/events?price_max=5000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $cheap->id);
});

it('treats a maximum price of zero as a filter', function () {
    $free = Event::factory()->create();
    TicketType::factory()->for($free)->create(['price' => 0]);
    TicketType::factory()->for(Event::factory())->create(['price' => 100]);

    $this->getJson('/api/events?price_max=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $free->id);
});

it('searches titles with q', function () {
    $jazz = Event::factory()->create(['title' => 'Krakow Jazz Night']);
    Event::factory()->create(['title' => 'Laravel Meetup']);

    $this->getJson('/api/events?q=jazz')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $jazz->id);
});

it('treats wildcards in q as plain text', function () {
    Event::factory()->create(['title' => 'Laravel Meetup']);
    $discount = Event::factory()->create(['title' => 'Sale 50% off']);

    $this->getJson('/api/events?q='.urlencode('%'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $discount->id);
});

it('ignores empty filters', function () {
    Event::factory()->count(2)->create();

    $this->getJson('/api/events?q=&city=')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sorts by a whitelisted field and rejects others with 400', function () {
    $later = Event::factory()->create(['starts_at' => '2027-05-01 18:00:00', 'ends_at' => '2027-05-01 20:00:00']);
    $sooner = Event::factory()->create(['starts_at' => '2027-04-01 18:00:00', 'ends_at' => '2027-04-01 20:00:00']);

    expect($this->getJson('/api/events')->json('data.*.id'))->toBe([$sooner->id, $later->id]);
    expect($this->getJson('/api/events?sort=-startsAt')->json('data.*.id'))->toBe([$later->id, $sooner->id]);

    $this->getJson('/api/events?sort=organizer_id')
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'sort');
});

it('breaks sort ties by id', function () {
    $events = Event::factory()->count(3)->create([
        'starts_at' => '2027-04-01 18:00:00',
        'ends_at' => '2027-04-01 20:00:00',
    ]);

    expect($this->getJson('/api/events?sort=-startsAt')->json('data.*.id'))
        ->toBe($events->pluck('id')->all());
});

it('runs a constant number of queries for a list', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/events')->assertOk();

        return count(DB::getQueryLog());
    };

    TicketType::factory()->for(Event::factory())->create();
    $forOneEvent = $countQueries();

    Event::factory()->count(5)->has(TicketType::factory()->count(2))->create();

    expect($countQueries())->toBe($forOneEvent);
});

it('returns dates in UTC with Z and the event timezone', function () {
    Event::factory()->create([
        'timezone' => 'America/New_York',
        'starts_at' => '2027-01-15 18:30:00',
        'ends_at' => '2027-01-15 21:00:00',
    ]);

    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonPath('data.0.timezone', 'America/New_York')
        ->assertJsonPath('data.0.startsAt', '2027-01-15T18:30:00Z')
        ->assertJsonPath('data.0.endsAt', '2027-01-15T21:00:00Z');
});

it('shows the details of a published event with its ticket types', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'name' => 'Standard',
        'price' => 12000,
        'quantity' => 10,
        'sold' => 3,
        'held' => 2,
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('id', $event->id)
        ->assertJsonPath('title', $event->title)
        ->assertJsonPath('ticketTypes', [[
            'id' => $ticketType->id,
            'name' => 'Standard',
            'price' => 12000,
            'available' => 5,
        ]]);
});

it('answers 404 for an event outside the catalog', function (string $state) {
    $event = Event::factory()->{$state}()->create();

    $this->getJson("/api/events/{$event->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
})->with(['draft', 'cancelled', 'finished']);

it('answers 404 for an id that cannot exist', function (string $id) {
    $this->getJson("/api/events/{$id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
})->with(['999999', 'abc', '99999999999999999999999']);

it('hides started events from the list but still shows them by id', function (string $startsAt, bool $listed) {
    $this->travelTo('2026-11-20 18:00:00');

    $event = Event::factory()->create(['starts_at' => $startsAt, 'ends_at' => '2026-11-20 23:00:00']);

    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount($listed ? 1 : 0, 'data');

    // Orders and tickets keep linking to the event after it starts.
    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('id', $event->id);
})->with([
    'one second before the start' => ['2026-11-20 18:00:01', true],
    'exactly at the start' => ['2026-11-20 18:00:00', false],
    'still going on' => ['2026-11-20 17:00:00', false],
]);
