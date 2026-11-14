<?php

use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Models\User;

beforeEach(function () {
    $this->organizer = User::factory()->organizer()->create();
    $this->event = Event::factory()->for($this->organizer, 'organizer')->create();
});

it('shows the sales summary of an own event', function () {
    $this->travelTo('2026-11-20 12:00:00');

    EventSalesSummary::factory()->for($this->event)->create([
        'tickets_sold' => 5,
        'revenue' => 25000,
        'refunds' => 5000,
        'checked_in' => 2,
    ]);

    $this->actingAs($this->organizer)
        ->getJson("/api/organizer/events/{$this->event->id}/sales")
        ->assertOk()
        ->assertExactJson([
            'eventId' => $this->event->id,
            'ticketsSold' => 5,
            'revenue' => 25000,
            'refunds' => 5000,
            'checkedIn' => 2,
            'updatedAt' => '2026-11-20T12:00:00Z',
        ]);
});

it('shows zeros before the first sale', function () {
    $this->actingAs($this->organizer)
        ->getJson("/api/organizer/events/{$this->event->id}/sales")
        ->assertOk()
        ->assertJsonPath('ticketsSold', 0)
        ->assertJsonPath('revenue', 0)
        ->assertJsonPath('updatedAt', null);
});

it('answers 403 for an event of another organizer', function () {
    $this->actingAs(User::factory()->organizer()->create())
        ->getJson("/api/organizer/events/{$this->event->id}/sales")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('answers 404 for a missing event', function () {
    $this->actingAs($this->organizer)
        ->getJson('/api/organizer/events/999999/sales')
        ->assertNotFound();
});

it('lets only organizers see sales', function () {
    $this->getJson("/api/organizer/events/{$this->event->id}/sales")->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/organizer/events/{$this->event->id}/sales")
        ->assertForbidden();
});
