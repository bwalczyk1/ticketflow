<?php

use App\Enums\EventStatus;
use App\Events\Catalog\EventCancelled;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Events\OrganizerEvents;
use Illuminate\Support\Facades\Event as EventBus;

function eventPayload(array $overrides = []): array
{
    return [
        'title' => 'Krakow Jazz Night',
        'description' => 'An evening of live jazz.',
        'venue' => 'Klub Re',
        'city' => 'Krakow',
        'category' => 'concert',
        'startsAt' => '2027-03-01T19:00:00+01:00',
        'endsAt' => '2027-03-01T22:00:00+01:00',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->organizer = User::factory()->organizer()->create();
    $this->event = Event::factory()->draft()->for($this->organizer, 'organizer')->create();
});

it('creates a draft event owned by the logged in organizer', function () {
    $response = $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload())
        ->assertCreated()
        ->assertJsonPath('title', 'Krakow Jazz Night')
        ->assertJsonPath('status', 'DRAFT')
        ->assertJsonPath('timezone', 'Europe/Warsaw')
        ->assertJsonPath('startsAt', '2027-03-01T18:00:00Z')
        ->assertJsonPath('endsAt', '2027-03-01T21:00:00Z')
        ->assertJsonPath('ticketTypes', []);

    $this->assertDatabaseHas('events', [
        'id' => $response->json('id'),
        'organizer_id' => $this->organizer->id,
        'starts_at' => '2027-03-01 18:00:00',
    ]);
});

it('stores the given timezone', function () {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload(['timezone' => 'America/New_York']))
        ->assertCreated()
        ->assertJsonPath('timezone', 'America/New_York');
});

it('accepts dates with milliseconds and Z', function () {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload([
            'startsAt' => '2027-03-01T18:00:00.000Z',
            'endsAt' => '2027-03-01T21:00:00.000Z',
        ]))
        ->assertCreated()
        ->assertJsonPath('startsAt', '2027-03-01T18:00:00Z');
});

it('rejects organizer_id in the body with 400', function (string $field, mixed $value) {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload([$field => $value]))
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    ['organizer_id', 1],
    ['organizerId', 1],
    ['status', 'PUBLISHED'],
]);

it('rejects dates without a timezone offset with 400', function (string $field, string $value) {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload([$field => $value]))
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    'naive start' => ['startsAt', '2027-03-01T19:00:00'],
    'naive end' => ['endsAt', '2027-03-01T22:00:00'],
    'only a day' => ['startsAt', '2027-03-01'],
    'impossible date' => ['startsAt', '2027-02-30T19:00:00Z'],
]);

it('rejects invalid event fields with 400', function (array $overrides, string $field) {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload($overrides))
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    'missing title' => [['title' => null], 'title'],
    'title too long' => [['title' => str_repeat('a', 161)], 'title'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'end before start' => [['endsAt' => '2027-03-01T18:00:00+01:00'], 'endsAt'],
    'end equal to start' => [['endsAt' => '2027-03-01T18:00:00Z'], 'endsAt'],
]);

it('lists only the events of the organizer', function () {
    $published = Event::factory()->published()->for($this->organizer, 'organizer')->create();
    Event::factory()->create();

    $response = $this->actingAs($this->organizer)
        ->getJson('/api/organizer/events?sort=createdAt')
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 2, 'totalPages' => 1]);

    expect($response->json('data.*.id'))->toBe([$this->event->id, $published->id]);
});

it('filters the organizer list by status', function () {
    Event::factory()->published()->for($this->organizer, 'organizer')->create();

    $this->actingAs($this->organizer)
        ->getJson('/api/organizer/events?status=DRAFT')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->event->id);

    $this->actingAs($this->organizer)
        ->getJson('/api/organizer/events?status=OPEN')
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'status');
});

it('shows an own event with its sales numbers', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 3, 'held' => 2]);

    $this->actingAs($this->organizer)
        ->getJson("/api/organizer/events/{$this->event->id}")
        ->assertOk()
        ->assertJsonPath('id', $this->event->id)
        ->assertJsonPath('status', 'DRAFT')
        ->assertJsonPath('ticketTypes.0.id', $ticketType->id)
        ->assertJsonPath('ticketTypes.0.quantity', 10)
        ->assertJsonPath('ticketTypes.0.sold', 3)
        ->assertJsonPath('ticketTypes.0.held', 2)
        ->assertJsonPath('ticketTypes.0.available', 5);
});

it('updates an event', function () {
    $this->actingAs($this->organizer)
        ->patchJson("/api/organizer/events/{$this->event->id}", [
            'title' => 'New title',
            'startsAt' => '2027-06-01T12:00:00+02:00',
            'endsAt' => '2027-06-01T14:00:00+02:00',
        ])
        ->assertOk()
        ->assertJsonPath('title', 'New title')
        ->assertJsonPath('startsAt', '2027-06-01T10:00:00Z');

    $this->assertDatabaseHas('events', [
        'id' => $this->event->id,
        'title' => 'New title',
        'venue' => $this->event->venue,
        'ends_at' => '2027-06-01 12:00:00',
    ]);
});

it('compares a new end with the stored start', function () {
    $this->event->update(['starts_at' => '2027-03-01 18:00:00', 'ends_at' => '2027-03-01 20:00:00']);

    $this->actingAs($this->organizer)
        ->patchJson("/api/organizer/events/{$this->event->id}", ['endsAt' => '2027-03-01T18:00:00Z'])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'endsAt');
});

it('translates the message for an end before the start', function (array $headers, string $message) {
    $this->actingAs($this->organizer)
        ->postJson('/api/organizer/events', eventPayload(['endsAt' => '2027-03-01T18:00:00+01:00']), $headers)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'endsAt')
        ->assertJsonPath('error.details.0.message', $message);
})->with([
    'English by default' => [[], 'The end must be after the start.'],
    'Polish on request' => [['Accept-Language' => 'pl'], 'Data zakończenia musi być późniejsza niż data rozpoczęcia.'],
]);

it('rejects status in an update with 400', function () {
    $this->actingAs($this->organizer)
        ->patchJson("/api/organizer/events/{$this->event->id}", ['status' => 'PUBLISHED'])
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'status');

    expect($this->event->fresh()->status)->toBe(EventStatus::Draft);
});

it('answers 409 EVENT_CLOSED when updating a closed event', function (string $state) {
    $event = Event::factory()->{$state}()->for($this->organizer, 'organizer')->create();

    $this->actingAs($this->organizer)
        ->patchJson("/api/organizer/events/{$event->id}", ['title' => 'New title'])
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_CLOSED');
})->with(['cancelled', 'finished']);

it('deletes an event without sales', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    $this->actingAs($this->organizer)
        ->deleteJson("/api/organizer/events/{$this->event->id}")
        ->assertNoContent();

    $this->assertModelMissing($this->event);
    $this->assertModelMissing($ticketType);
});

it('answers 409 when deleting an event with sold tickets', function (array $pool) {
    TicketType::factory()->for($this->event)->create($pool);

    $this->actingAs($this->organizer)
        ->deleteJson("/api/organizer/events/{$this->event->id}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_HAS_SALES');

    $this->assertModelExists($this->event);
})->with([
    'sold' => [['sold' => 1]],
    'held' => [['held' => 1]],
]);

it('keeps an event with order history', function () {
    Order::factory()->expired()->for($this->event)->create();

    $this->actingAs($this->organizer)
        ->deleteJson("/api/organizer/events/{$this->event->id}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'FOREIGN_KEY_VIOLATION');

    $this->assertModelExists($this->event);
});

it('answers 403 for an event of another organizer', function (string $method, string $path) {
    $foreign = Event::factory()->create(['title' => 'Foreign']);

    $this->actingAs($this->organizer)
        ->json($method, sprintf($path, $foreign->id))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($foreign->fresh()->status)->toBe(EventStatus::Published);
})->with([
    ['GET', '/api/organizer/events/%d'],
    ['PATCH', '/api/organizer/events/%d'],
    ['DELETE', '/api/organizer/events/%d'],
    ['POST', '/api/organizer/events/%d/publish'],
    ['POST', '/api/organizer/events/%d/cancel'],
]);

it('answers 404 for an unknown event', function (string $id) {
    $this->actingAs($this->organizer)
        ->getJson("/api/organizer/events/{$id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
})->with(['999999', 'abc', '99999999999999999999999']);

it('answers 403 for an attendee', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/organizer/events')
        ->assertForbidden();
});

it('answers 403 for an admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/organizer/events')
        ->assertForbidden();
});

it('answers 401 for a guest', function () {
    $this->getJson('/api/organizer/events')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('checks in the order 401, 403 role, 400, 404, 403 ownership, 409', function () {
    $path = '/api/organizer/events/';
    $valid = ['title' => 'New title'];
    $invalid = ['title' => str_repeat('a', 161)];
    $foreign = Event::factory()->create();
    $closed = Event::factory()->cancelled()->for($this->organizer, 'organizer')->create();

    $this->patchJson($path.'999999', $invalid)->assertUnauthorized();

    $this->actingAs(User::factory()->create())->patchJson($path.'999999', $invalid)->assertForbidden();

    $this->actingAs($this->organizer);

    $this->patchJson($path.'999999', $invalid)->assertStatus(400);
    $this->patchJson($path.'999999', $valid)->assertNotFound();
    $this->patchJson($path.$foreign->id, $valid)->assertForbidden();
    $this->patchJson($path.$closed->id, $valid)->assertConflict();
});

it('answers 403 before 404 for an attendee on a missing event', function (string $method, string $suffix) {
    $this->actingAs(User::factory()->create())
        ->json($method, "/api/organizer/events/999999{$suffix}")
        ->assertForbidden();
})->with([
    ['GET', ''],
    ['DELETE', ''],
    ['POST', '/publish'],
    ['POST', '/cancel'],
    ['GET', '/ticket-types'],
    ['GET', '/ticket-types/999999'],
]);

it('publishes a draft event with at least one ticket type', function () {
    TicketType::factory()->for($this->event)->create();

    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$this->event->id}/publish")
        ->assertOk()
        ->assertJsonPath('status', 'PUBLISHED');

    $this->getJson("/api/events/{$this->event->id}")->assertOk();
});

it('answers 409 EVENT_NOT_PUBLISHABLE without ticket types', function () {
    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$this->event->id}/publish")
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_NOT_PUBLISHABLE');

    expect($this->event->fresh()->status)->toBe(EventStatus::Draft);
});

it('answers 409 EVENT_NOT_PUBLISHABLE for a past start date', function () {
    TicketType::factory()->for($this->event)->create();
    $this->travelTo($this->event->starts_at);

    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$this->event->id}/publish")
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_NOT_PUBLISHABLE');
});

it('answers 409 INVALID_STATUS_TRANSITION for a forbidden status change', function (string $state, string $action) {
    EventBus::fake([EventCancelled::class]);

    $event = Event::factory()->{$state}()->for($this->organizer, 'organizer')->create();
    TicketType::factory()->for($event)->create();

    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$event->id}/{$action}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');

    EventBus::assertNotDispatched(EventCancelled::class);
})->with([
    ['published', 'publish'],
    ['cancelled', 'publish'],
    ['finished', 'publish'],
    ['draft', 'cancel'],
    ['cancelled', 'cancel'],
    ['finished', 'cancel'],
]);

it('cancels a published event and dispatches EventCancelled', function () {
    EventBus::fake([EventCancelled::class]);

    $event = Event::factory()->published()->for($this->organizer, 'organizer')->create();

    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$event->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', 'CANCELLED');

    EventBus::assertDispatched(EventCancelled::class, fn (EventCancelled $cancelled) => $cancelled->eventId === $event->id);
    EventBus::assertDispatchedTimes(EventCancelled::class, 1);

    $this->getJson("/api/events/{$event->id}")->assertNotFound();
});

it('cancels an event with sold tickets', function () {
    EventBus::fake([EventCancelled::class]);

    $event = Event::factory()->published()->for($this->organizer, 'organizer')->create();
    TicketType::factory()->for($event)->create(['sold' => 5]);

    $this->actingAs($this->organizer)
        ->postJson("/api/organizer/events/{$event->id}/cancel")
        ->assertOk();

    EventBus::assertDispatched(EventCancelled::class);
});

it('lets only the first of two parallel cancellations dispatch EventCancelled', function () {
    EventBus::fake([EventCancelled::class]);

    $event = Event::factory()->published()->for($this->organizer, 'organizer')->create();
    $stale = $event->fresh();
    $events = app(OrganizerEvents::class);

    $events->cancel($event);

    expect(fn () => $events->cancel($stale))->toThrow(InvalidStatusTransition::class);

    EventBus::assertDispatchedTimes(EventCancelled::class, 1);
});
