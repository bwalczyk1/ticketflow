<?php

use App\Enums\WaitlistStatus;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use App\Services\Waitlist\WaitlistOffers;
use Illuminate\Support\Facades\Event;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    Event::fake([WaitlistOfferCreated::class]);

    $this->pool = TicketType::factory()->create(['quantity' => 10, 'sold' => 8]);
    $this->entry = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 2]);
    $this->other = secondConnection();
});

it('leaves the queue waiting when a normal purchase takes the seats first', function () {
    // A buyer reserves the two free seats after the offer read the pool. Once the offer updates an
    // entry, the foreign key check locks the pool row, so the buyer's step comes right before that.
    Interleave::before('update `waitlist_entries`', function () {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['held' => 2, 'version' => 1]);
    });

    expect(app(WaitlistOffers::class)->offerFreeSeats($this->pool->id))->toBe([]);

    expect($this->pool->fresh())->held->toBe(2)->version->toBe(1)
        ->and($this->entry->fresh())->status->toBe(WaitlistStatus::Waiting)->offered_until->toBeNull();

    Event::assertNotDispatched(WaitlistOfferCreated::class);
});

it('retries when the pool changes without taking the seats', function () {
    Interleave::before('update `waitlist_entries`', function () {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['version' => 5]);
    });

    expect(app(WaitlistOffers::class)->offerFreeSeats($this->pool->id))->toBe([$this->entry->id]);

    expect($this->pool->fresh())->held->toBe(2)->version->toBe(6)
        ->and($this->entry->fresh()->status)->toBe(WaitlistStatus::Offered);

    Event::assertDispatchedTimes(WaitlistOfferCreated::class, 1);
});

it('does not hold seats for an entry that left the queue meanwhile', function () {
    Interleave::before('update `waitlist_entries`', function () {
        $this->other->table('waitlist_entries')->where('id', $this->entry->id)->update(['status' => 'CANCELLED']);
    });

    expect(app(WaitlistOffers::class)->offerFreeSeats($this->pool->id))->toBe([]);

    expect($this->pool->fresh()->held)->toBe(0)
        ->and($this->entry->fresh()->status)->toBe(WaitlistStatus::Cancelled);
});
