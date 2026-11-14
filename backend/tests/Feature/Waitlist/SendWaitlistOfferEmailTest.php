<?php

use App\Enums\QueueName;
use App\Events\Waitlist\WaitlistOfferCreated;
use App\Listeners\Waitlist\SendWaitlistOfferEmail;
use App\Mail\WaitlistOfferMail;
use App\Models\Event as EventModel;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00:00');

    $event = EventModel::factory()->create(['title' => 'Jazz w Piwnicy', 'timezone' => 'Europe/Warsaw']);
    $pool = TicketType::factory()->for($event)->create(['name' => 'Parter']);

    $this->user = User::factory()->create(['email' => 'anna@example.com']);
    $this->entry = WaitlistEntry::factory()->for($this->user)->for($pool)->offered()->create(['quantity' => 2]);
});

function offerCreated(WaitlistEntry $entry): void
{
    app(SendWaitlistOfferEmail::class)->handle(new WaitlistOfferCreated($entry->id));
}

it('sends the offer with the deadline in the event time zone and a link to the app', function () {
    Mail::fake();

    offerCreated($this->entry);

    Mail::assertSent(WaitlistOfferMail::class, function (WaitlistOfferMail $mail) {
        // 12:30 UTC is 13:30 in Warsaw in November.
        $mail->assertSeeInHtml('13:30')
            ->assertSeeInHtml('20.11.2026')
            ->assertSeeInHtml('Jazz w Piwnicy')
            ->assertSeeInHtml('Parter')
            ->assertSeeInHtml(rtrim(config('app.url'), '/').'/waitlist');

        return $mail->hasTo('anna@example.com') && str_contains($mail->envelope()->subject, 'Jazz w Piwnicy');
    });
});

it('sends the offer once when the job runs twice', function () {
    Mail::fake();

    offerCreated($this->entry);
    offerCreated($this->entry);

    Mail::assertSentCount(1);
});

it('sends nothing for an offer that is no longer open', function (string $status) {
    Mail::fake();

    $this->entry->update(['status' => $status]);

    offerCreated($this->entry);

    Mail::assertNothingSent();
})->with(['CONVERTED', 'EXPIRED', 'CANCELLED']);

it('listens to new offers on the mail queue', function () {
    Event::fake();

    Event::assertListening(WaitlistOfferCreated::class, SendWaitlistOfferEmail::class);
});

it('queues the offer e-mail on the mail queue', function () {
    Queue::fake();

    event(new WaitlistOfferCreated($this->entry->id));

    Queue::assertPushedOn(
        QueueName::Mail->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === SendWaitlistOfferEmail::class,
    );
});
