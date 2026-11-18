<?php

use App\Enums\EventStatus;
use App\Enums\QueueName;
use App\Enums\WaitlistStatus;
use App\Events\Catalog\EventCancelled;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Listeners\Waitlist\CloseWaitlist;
use App\Models\Event as EventModel;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->event = EventModel::factory()->create();
    $this->pool = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 7, 'held' => 3]);
    $this->secondPool = TicketType::factory()->for($this->event)->create(['quantity' => 5, 'sold' => 5]);

    $this->offer = WaitlistEntry::factory()->for($this->pool)->offered()->create(['quantity' => 2]);
    $this->waiting = WaitlistEntry::factory()->for($this->pool)->create(['quantity' => 2]);
    $this->waitingForSecond = WaitlistEntry::factory()->for($this->secondPool)->create();
    $this->converted = WaitlistEntry::factory()->for($this->pool)->converted()->create();

    $this->event->forceFill(['status' => EventStatus::Cancelled])->save();
});

function closeWaitlist(EventModel $event): void
{
    app(CloseWaitlist::class)->handle(new EventCancelled($event->id));
}

it('closes every active entry of the cancelled event', function () {
    closeWaitlist($this->event);

    expect($this->offer->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($this->waiting->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($this->waitingForSecond->fresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($this->converted->fresh()->status)->toBe(WaitlistStatus::Converted);
});

it('returns offered seats to the pool without offering them again', function () {
    Event::fake([WaitlistOfferCreated::class]);

    closeWaitlist($this->event);

    // One seat stays held by a pending order of the event, which the cancellation handles itself.
    expect($this->pool->fresh())->held->toBe(1)->version->toBe(1);

    Event::assertNotDispatched(WaitlistOfferCreated::class);
});

it('gives the seats back only once when it runs twice', function () {
    closeWaitlist($this->event);
    closeWaitlist($this->event);

    expect($this->pool->fresh()->held)->toBe(1);
});

it('leaves the waitlists of other events alone', function () {
    $other = WaitlistEntry::factory()->offered()->create();

    closeWaitlist($this->event);

    expect($other->fresh()->status)->toBe(WaitlistStatus::Offered);
});

it('listens to cancelled events', function () {
    Event::fake();

    Event::assertListening(EventCancelled::class, CloseWaitlist::class);
});

it('closes the waitlist on the waitlist queue', function () {
    Queue::fake();

    event(new EventCancelled($this->event->id));

    Queue::assertPushedOn(
        QueueName::Waitlist->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === CloseWaitlist::class,
    );
});
