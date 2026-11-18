<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->event = Event::factory()->published()->create();
    $this->pool = TicketType::factory()->for($this->event)->create(['quantity' => 50]);
    $this->attendee = User::factory()->create();
    $this->path = "/api/events/{$this->event->id}";
});

it('shows a null purchase limit to a guest', function () {
    expect($this->getJson($this->path)->assertOk()->json())->toHaveKey('purchaseLimit', null);
});

it('shows the purchase limit of a signed-in attendee', function () {
    $this->actingAs($this->attendee)
        ->getJson($this->path)
        ->assertOk()
        ->assertJsonPath('purchaseLimit', ['max' => 6, 'used' => 0]);
});

it('counts the tickets of pending and paid orders for this event only', function () {
    OrderFixtures::holding($this->pool, 2, $this->attendee);
    OrderFixtures::holding($this->pool, 1, $this->attendee, ['status' => OrderStatus::Paid]);
    OrderFixtures::holding($this->pool, 3, $this->attendee, ['status' => OrderStatus::Cancelled]);
    OrderFixtures::holding($this->pool, 4, $this->attendee, ['status' => OrderStatus::Failed]);
    OrderFixtures::holding($this->pool, 5, User::factory()->create());
    OrderFixtures::holding(TicketType::factory()->create(), 4, $this->attendee);

    $this->actingAs($this->attendee)
        ->getJson($this->path)
        ->assertJsonPath('purchaseLimit', ['max' => 6, 'used' => 3]);
});

it('agrees with the limit enforced when reserving', function () {
    OrderFixtures::holding($this->pool, 4, $this->attendee);

    $this->actingAs($this->attendee);

    $used = $this->getJson($this->path)->json('purchaseLimit.used');

    $this->postJson('/api/orders', ['eventId' => $this->event->id, 'items' => [['ticketTypeId' => $this->pool->id, 'quantity' => 6 - $used + 1]]])
        ->assertConflict()
        ->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');

    $this->postJson('/api/orders', ['eventId' => $this->event->id, 'items' => [['ticketTypeId' => $this->pool->id, 'quantity' => 6 - $used]]])
        ->assertCreated();
});

it('reads the attendee from a bearer token as well', function () {
    $token = $this->attendee->createToken('test')->plainTextToken;

    $this->getJson($this->path, ['Authorization' => "Bearer {$token}"])
        ->assertJsonPath('purchaseLimit', ['max' => 6, 'used' => 0]);
});

it('shows a null purchase limit to roles that cannot buy', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    expect($this->getJson($this->path)->assertOk()->json())->toHaveKey('purchaseLimit', null);
})->with(['organizer', 'admin']);

it('keeps the purchase limit out of the catalog list', function () {
    $this->actingAs($this->attendee)
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonMissingPath('data.0.purchaseLimit');
});
