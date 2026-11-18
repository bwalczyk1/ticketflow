<?php

use App\Enums\WaitlistStatus;
use App\Exceptions\Domain\WaitlistOfferExpired;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Waitlist\AcceptWaitlistOffer;
use App\Services\Waitlist\ExpireWaitlistOffers;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    $this->travelTo('2026-11-14 12:29:59');

    $this->user = User::factory()->create();
    $this->pool = TicketType::factory()->create(['quantity' => 10, 'sold' => 8, 'held' => 2]);
    $this->offer = WaitlistEntry::factory()->for($this->user)->for($this->pool)->create([
        'quantity' => 2,
        'status' => WaitlistStatus::Offered,
        'offered_until' => '2026-11-14 12:30:00',
    ]);
    $this->other = secondConnection();
});

it('answers 410 when the scheduler expires the offer just before the accept starts', function () {
    // Later steps of the scheduler cannot slip in: expiring the entry checks its user row, which the
    // accept locks first, so the scheduler waits for the accept to finish.
    Interleave::before('from `users` where `users`.`id` = ?', function () {
        $this->other->transaction(function () {
            $this->other->table('waitlist_entries')->where('id', $this->offer->id)->update(['status' => 'EXPIRED']);
            $this->other->table('ticket_types')->where('id', $this->pool->id)->decrement('held', 2);
        });
    });

    expect(fn () => app(AcceptWaitlistOffer::class)->handle($this->user, $this->offer->id))
        ->toThrow(WaitlistOfferExpired::class);

    expect(Order::query()->count())->toBe(0)
        ->and($this->pool->fresh()->held)->toBe(0);
});

it('leaves an accepted offer alone when the scheduler runs right after it', function () {
    app(AcceptWaitlistOffer::class)->handle($this->user, $this->offer->id);

    $this->travelTo('2026-11-14 12:30:00');

    expect(app(ExpireWaitlistOffers::class)->handle())->toBe(0)
        ->and($this->offer->fresh()->status)->toBe(WaitlistStatus::Converted)
        ->and($this->pool->fresh()->held)->toBe(2);
});
