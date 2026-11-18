<?php

use App\Enums\WaitlistStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Listeners\Documents\IssueInvoice;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
    Event::fake([WaitlistOfferCreated::class]);

    $this->attendee = User::factory()->create();
    $this->order = TicketFixtures::paidOrder([2], $this->attendee);
    TicketFixtures::issueTickets($this->order);

    // The pool is sold out, so two people wait for it.
    $this->pool = TicketType::query()->where('event_id', $this->order->event_id)->sole();
    $this->pool->forceFill(['quantity' => 2])->save();

    $this->first = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 2]);
    $this->second = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 1]);

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));
});

it('offers the seats of a refunded order to the first person in line', function () {
    $this->actingAs($this->attendee)->postJson("/api/orders/{$this->order->id}/refund")->assertOk();

    expect($this->first->fresh()->status)->toBe(WaitlistStatus::Offered)
        ->and($this->second->fresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and($this->pool->fresh())->sold->toBe(0)->held->toBe(2);

    Event::assertDispatchedTimes(WaitlistOfferCreated::class, 1);
    Event::assertDispatched(WaitlistOfferCreated::class, fn (WaitlistOfferCreated $event) => $event->waitlistEntryId === $this->first->id);
});

it('offers nothing when the seats come back because the event was cancelled', function () {
    $this->actingAs($this->order->event->organizer)
        ->postJson("/api/organizer/events/{$this->order->event_id}/cancel")
        ->assertOk();

    expect($this->first->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($this->second->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($this->pool->fresh())->sold->toBe(0)->held->toBe(0);

    Event::assertNotDispatched(WaitlistOfferCreated::class);
});
