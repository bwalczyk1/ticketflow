<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists events of all organizers for an admin', function () {
    $olivia = User::factory()->organizer()->create();
    $oscar = User::factory()->organizer()->create();

    $events = collect([
        Event::factory()->draft()->for($olivia, 'organizer')->create(),
        Event::factory()->published()->for($olivia, 'organizer')->create(),
        Event::factory()->cancelled()->for($oscar, 'organizer')->create(),
        Event::factory()->finished()->for($oscar, 'organizer')->create(),
    ]);

    $this->actingAs($this->admin);

    $response = $this->getJson('/api/admin/events')->assertOk()->assertJsonCount(4, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing($events->pluck('id')->all())
        ->and($response->json('data.*.organizer.id'))->toContain($olivia->id, $oscar->id);
});

it('returns the event with its organizer and UTC dates', function () {
    $organizer = User::factory()->organizer()->create(['name' => 'Olivia', 'email' => 'olivia@example.com']);

    $event = Event::factory()->for($organizer, 'organizer')->create([
        'title' => 'Jazz Night',
        'venue' => 'Klub Pod Jaszczurami',
        'city' => 'Krakow',
        'category' => 'concert',
        'starts_at' => '2026-12-05 18:00:00',
        'ends_at' => '2026-12-05 21:00:00',
    ]);

    $this->actingAs($this->admin);

    $this->getJson('/api/admin/events')
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $event->id,
                'title' => 'Jazz Night',
                'status' => 'PUBLISHED',
                'venue' => 'Klub Pod Jaszczurami',
                'city' => 'Krakow',
                'category' => 'concert',
                'timezone' => 'Europe/Warsaw',
                'startsAt' => '2026-12-05T18:00:00Z',
                'endsAt' => '2026-12-05T21:00:00Z',
                'organizer' => [
                    'id' => $organizer->id,
                    'name' => 'Olivia',
                    'email' => 'olivia@example.com',
                    'role' => 'ORGANIZER',
                ],
            ]],
            'meta' => ['page' => 1, 'limit' => 20, 'total' => 1, 'totalPages' => 1],
        ]);
});

it('paginates and sorts events', function () {
    $soon = Event::factory()->create(['starts_at' => now()->addDays(1), 'ends_at' => now()->addDays(2)]);
    $later = Event::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(4)]);
    $last = Event::factory()->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(6)]);

    $this->actingAs($this->admin);

    $this->getJson('/api/admin/events?sort=startsAt&limit=2')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$soon->id, $later->id])
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 2, 'total' => 3, 'totalPages' => 2]);

    $this->getJson('/api/admin/events?limit=1')->assertJsonPath('data.0.id', $last->id);
});

it('rejects a sort outside the whitelist with 400', function () {
    $this->actingAs($this->admin);

    $this->getJson('/api/admin/events?sort=description')
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'sort');
});

it('answers 401 without a session', function () {
    $this->getJson('/api/admin/events')->assertUnauthorized();
});

it('answers 403 for an organizer and an attendee', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->getJson('/api/admin/events')->assertForbidden();
})->with(['ORGANIZER', 'ATTENDEE']);
