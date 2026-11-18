<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Enums\WaitlistStatus;
use App\Events\Orders\SeatsReleased;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Exceptions\Domain\TicketsSoldOut;
use App\Listeners\Waitlist\OfferSeatsToWaitlist;
use App\Models\Event as EventModel;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Orders\ReleaseOrderSeats;
use App\Services\Orders\ReserveTickets;
use App\Services\Waitlist\WaitlistOffers;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->freezeTime();

    $this->pool = TicketType::factory()->create(['quantity' => 10, 'sold' => 8]);
});

function waiting(TicketType $pool, int $quantity): WaitlistEntry
{
    return WaitlistEntry::factory()->for($pool)->create(['quantity' => $quantity]);
}

it('offers free seats to the queue in the order of joining for 30 minutes', function () {
    Event::fake([WaitlistOfferCreated::class]);

    $first = waiting($this->pool, 1);
    $tooLarge = waiting($this->pool, 2);
    $third = waiting($this->pool, 1);

    $offered = app(WaitlistOffers::class)->offerFreeSeats($this->pool->id);

    expect($offered)->toBe([$first->id, $third->id])
        ->and($this->pool->fresh())->held->toBe(2)->version->toBe(1)
        ->and($first->fresh())->status->toBe(WaitlistStatus::Offered)
        ->and($first->fresh()->offered_until->toDateTimeString())->toBe(now()->addMinutes(30)->toDateTimeString())
        ->and($tooLarge->fresh())->status->toBe(WaitlistStatus::Waiting)->offered_until->toBeNull()
        ->and($third->fresh()->status)->toBe(WaitlistStatus::Offered);

    Event::assertDispatchedTimes(WaitlistOfferCreated::class, 2);
    Event::assertDispatched(WaitlistOfferCreated::class, fn (WaitlistOfferCreated $event) => $event->waitlistEntryId === $first->id);
});

it('offers the same seats only once when it runs twice', function () {
    Event::fake([WaitlistOfferCreated::class]);

    $entry = waiting($this->pool, 2);
    $next = waiting($this->pool, 1);

    app(WaitlistOffers::class)->offerFreeSeats($this->pool->id);
    app(WaitlistOffers::class)->offerFreeSeats($this->pool->id);

    expect($this->pool->fresh()->held)->toBe(2)
        ->and($entry->fresh()->status)->toBe(WaitlistStatus::Offered)
        ->and($next->fresh()->status)->toBe(WaitlistStatus::Waiting);

    Event::assertDispatchedTimes(WaitlistOfferCreated::class, 1);
});

it('offers nothing while the event is not on sale', function (Closure $closeSales) {
    Event::fake([WaitlistOfferCreated::class]);

    $entry = waiting($this->pool, 1);

    $closeSales($this->pool->event);

    expect(app(WaitlistOffers::class)->offerFreeSeats($this->pool->id))->toBe([])
        ->and($this->pool->fresh()->held)->toBe(0)
        ->and($entry->fresh()->status)->toBe(WaitlistStatus::Waiting);

    Event::assertNotDispatched(WaitlistOfferCreated::class);
})->with([
    'cancelled' => [fn (EventModel $event) => $event->forceFill(['status' => EventStatus::Cancelled])->save()],
    'already started' => [fn (EventModel $event) => test()->travelTo($event->starts_at)],
]);

it('keeps released seats away from other buyers when someone waits for them', function () {
    Event::fake([SeatsReleased::class, WaitlistOfferCreated::class]);

    $pool = TicketType::factory()->create(['quantity' => 2]);
    $order = OrderFixtures::holding($pool, 2, attributes: ['status' => OrderStatus::Expired]);
    $entry = waiting($pool, 2);

    app(ReleaseOrderSeats::class)->handle($order->id);

    // The release and the offer commit together, so the seats never show up as free.
    expect($pool->fresh()->held)->toBe(2)
        ->and($entry->fresh()->status)->toBe(WaitlistStatus::Offered);

    expect(fn () => app(ReserveTickets::class)->handle(User::factory()->create(), $pool->event_id, [
        ['ticketTypeId' => $pool->id, 'quantity' => 1],
    ]))->toThrow(TicketsSoldOut::class);

    Event::assertDispatched(SeatsReleased::class);
    Event::assertDispatched(WaitlistOfferCreated::class);
});

it('offers released seats when the listener runs', function () {
    Event::fake([WaitlistOfferCreated::class]);

    $entry = waiting($this->pool, 2);

    app(OfferSeatsToWaitlist::class)->handle(new SeatsReleased($this->pool->id, 2));

    expect($entry->fresh()->status)->toBe(WaitlistStatus::Offered)
        ->and($this->pool->fresh()->held)->toBe(2);
});

it('listens to released seats', function () {
    Event::fake();

    Event::assertListening(SeatsReleased::class, OfferSeatsToWaitlist::class);
});

it('offers seats on the waitlist queue', function () {
    Queue::fake();

    event(new SeatsReleased($this->pool->id, 1));

    Queue::assertPushedOn(
        QueueName::Waitlist->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === OfferSeatsToWaitlist::class,
    );
});
