<?php

use App\Models\Event;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->organizer = User::factory()->organizer()->create();
    $this->event = Event::factory()->draft()->for($this->organizer, 'organizer')->create();
    $this->path = "/api/organizer/events/{$this->event->id}/ticket-types";
    $this->actingAs($this->organizer);
});

/**
 * Simulates a sale that commits between the read and the conditional update, on every read.
 */
function sellOneTicketOnEveryRead(TicketType $ticketType, int $times = PHP_INT_MAX): void
{
    TicketType::retrieved(function () use ($ticketType, &$times) {
        if ($times-- > 0) {
            DB::table('ticket_types')->where('id', $ticketType->id)->update([
                'sold' => DB::raw('sold + 1'),
                'version' => DB::raw('version + 1'),
            ]);
        }
    });
}

it('creates a ticket type for an own event', function () {
    $response = $this->postJson($this->path, ['name' => 'Standard', 'price' => 12000, 'quantity' => 50])
        ->assertCreated()
        ->assertJsonPath('name', 'Standard')
        ->assertJsonPath('price', 12000)
        ->assertJsonPath('quantity', 50)
        ->assertJsonPath('sold', 0)
        ->assertJsonPath('held', 0)
        ->assertJsonPath('available', 50);

    $this->assertDatabaseHas('ticket_types', [
        'id' => $response->json('id'),
        'event_id' => $this->event->id,
        'version' => 0,
    ]);
});

it('labels the ticket type name as a pool name', function (array $headers, string $message) {
    $this->postJson($this->path, ['price' => 12000, 'quantity' => 50], $headers)
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'name')
        ->assertJsonPath('error.details.0.message', $message);
})->with([
    'English by default' => [[], 'The name field is required.'],
    'Polish on request' => [['Accept-Language' => 'pl'], 'Pole nazwa puli jest wymagane.'],
]);

it('labels the ticket type name in an update', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    $this->patchJson("{$this->path}/{$ticketType->id}", ['name' => str_repeat('a', 101)], ['Accept-Language' => 'pl'])
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.message', 'Pole nazwa puli nie może mieć więcej niż 100 znaków.');
});

it('rejects invalid ticket type fields with 400', function (array $body, string $field) {
    $this->postJson($this->path, [...['name' => 'Standard', 'price' => 12000, 'quantity' => 50], ...$body])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    'missing name' => [['name' => null], 'name'],
    'negative price' => [['price' => -1], 'price'],
    'price above the column range' => [['price' => 4294967296], 'price'],
    'empty pool' => [['quantity' => 0], 'quantity'],
    'quantity above the column range' => [['quantity' => 2147483648], 'quantity'],
    'sold in the body' => [['sold' => 5], 'sold'],
    'version in the body' => [['version' => 5], 'version'],
]);

it('answers 409 for a duplicate name within an event', function () {
    TicketType::factory()->for($this->event)->create(['name' => 'Standard']);

    $this->postJson($this->path, ['name' => 'Standard', 'price' => 12000, 'quantity' => 50])
        ->assertConflict()
        ->assertJsonPath('error.code', 'UNIQUE_VIOLATION');
});

it('lists the ticket types of an own event', function () {
    $cheap = TicketType::factory()->for($this->event)->create(['price' => 1000]);
    $expensive = TicketType::factory()->for($this->event)->create(['price' => 9000]);
    TicketType::factory()->create();

    $response = $this->getJson("{$this->path}?sort=-price")
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 2, 'totalPages' => 1]);

    expect($response->json('data.*.id'))->toBe([$expensive->id, $cheap->id]);
});

it('shows a ticket type of an own event', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 4]);

    $this->getJson("{$this->path}/{$ticketType->id}")
        ->assertOk()
        ->assertJsonPath('id', $ticketType->id)
        ->assertJsonPath('available', 6);
});

it('updates the price and quantity', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['price' => 1000, 'quantity' => 10, 'version' => 3]);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['price' => 2000, 'quantity' => 20])
        ->assertOk()
        ->assertJsonPath('price', 2000)
        ->assertJsonPath('quantity', 20);

    $this->assertDatabaseHas('ticket_types', [
        'id' => $ticketType->id,
        'price' => 2000,
        'quantity' => 20,
        'version' => 4,
    ]);
});

it('deletes a ticket type without sales', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    $this->deleteJson("{$this->path}/{$ticketType->id}")->assertNoContent();

    $this->assertModelMissing($ticketType);
});

it('answers 409 POOL_PROTECTED when deleting a ticket type with sales', function (array $pool) {
    $ticketType = TicketType::factory()->for($this->event)->create($pool);

    $this->deleteJson("{$this->path}/{$ticketType->id}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'POOL_PROTECTED');

    $this->assertModelExists($ticketType);
})->with([
    'sold' => [['sold' => 1]],
    'held' => [['held' => 1]],
]);

it('keeps a ticket type with order history', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();
    OrderItem::factory()->for($ticketType)->create();

    $this->deleteJson("{$this->path}/{$ticketType->id}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'FOREIGN_KEY_VIOLATION');

    $this->assertModelExists($ticketType);
});

it('answers 409 POOL_PROTECTED when quantity goes below sold plus held', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 3, 'held' => 2]);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['quantity' => 4])
        ->assertConflict()
        ->assertJsonPath('error.code', 'POOL_PROTECTED');

    expect($ticketType->fresh()->quantity)->toBe(10);
});

it('accepts quantity equal to sold plus held', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 3, 'held' => 2]);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('quantity', 5)
        ->assertJsonPath('available', 0);
});

it('changes quantity conditionally on version', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 2]);

    sellOneTicketOnEveryRead($ticketType, times: 1);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['quantity' => 8])
        ->assertOk()
        ->assertJsonPath('quantity', 8)
        ->assertJsonPath('sold', 3);

    $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'version' => 2]);
});

it('checks the pool again when a sale wins the race', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 7]);

    sellOneTicketOnEveryRead($ticketType, times: 1);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['quantity' => 7])
        ->assertConflict()
        ->assertJsonPath('error.code', 'POOL_PROTECTED');

    expect($ticketType->fresh()->quantity)->toBe(10);
});

it('answers 409 RESERVATION_CONFLICT when every attempt loses the race', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 100]);

    sellOneTicketOnEveryRead($ticketType);

    $this->patchJson("{$this->path}/{$ticketType->id}", ['quantity' => 90])
        ->assertConflict()
        ->assertJsonPath('error.code', 'RESERVATION_CONFLICT');

    expect($ticketType->fresh()->quantity)->toBe(100);
});

it('answers 409 EVENT_CLOSED for changes in a closed event', function (string $state) {
    $event = Event::factory()->{$state}()->for($this->organizer, 'organizer')->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $path = "/api/organizer/events/{$event->id}/ticket-types";

    $this->postJson($path, ['name' => 'Late', 'price' => 100, 'quantity' => 1])
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_CLOSED');

    $this->patchJson("{$path}/{$ticketType->id}", ['price' => 100])
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_CLOSED');

    $this->deleteJson("{$path}/{$ticketType->id}")
        ->assertConflict()
        ->assertJsonPath('error.code', 'EVENT_CLOSED');
})->with(['cancelled', 'finished']);

it('answers 404 before 409 for an unknown ticket type in a closed event', function (string $method) {
    $event = Event::factory()->cancelled()->for($this->organizer, 'organizer')->create();

    $this->json($method, "/api/organizer/events/{$event->id}/ticket-types/999999")
        ->assertNotFound();
})->with(['PATCH', 'DELETE']);

it('answers 403 for a ticket type of another organizer', function (string $method, string $suffix, array $body) {
    $ticketType = TicketType::factory()->create(['price' => 1000]);
    $path = "/api/organizer/events/{$ticketType->event_id}/ticket-types{$suffix}";

    $this->json($method, sprintf($path, $ticketType->id), $body)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($ticketType->fresh()->price)->toBe(1000);
    expect(TicketType::query()->count())->toBe(1);
})->with([
    ['GET', '', []],
    ['POST', '', ['name' => 'Taken', 'price' => 1, 'quantity' => 1]],
    ['GET', '/%d', []],
    ['PATCH', '/%d', ['price' => 1]],
    ['DELETE', '/%d', []],
]);

it('answers 404 for a ticket type of another event', function (string $method) {
    $foreign = TicketType::factory()->for(Event::factory()->for($this->organizer, 'organizer'))->create();

    $this->json($method, "{$this->path}/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');

    $this->assertModelExists($foreign);
})->with(['GET', 'PATCH', 'DELETE']);

it('answers 404 for a ticket type id that cannot exist', function (string $id) {
    $this->getJson("{$this->path}/{$id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
})->with(['abc', '99999999999999999999999']);

it('answers 403 for an attendee', function () {
    $this->actingAs(User::factory()->create())
        ->getJson($this->path)
        ->assertForbidden();
});
