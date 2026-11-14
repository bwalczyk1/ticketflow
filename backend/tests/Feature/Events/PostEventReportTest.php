<?php

use App\Enums\EventStatus;
use App\Enums\QueueName;
use App\Enums\TicketStatus;
use App\Events\Catalog\EventFinished;
use App\Listeners\Notifications\SendPostEventReport;
use App\Mail\PostEventReportMail;
use App\Models\Event as EventModel;
use App\Models\Refund;
use App\Models\User;
use App\Services\Events\FinishEndedEvents;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->travelTo('2026-11-20 21:00:00');

    $this->organizer = User::factory()->organizer()->create(['email' => 'org@example.com']);
    $this->event = EventModel::factory()->for($this->organizer, 'organizer')->create([
        'title' => 'Jazz w Piwnicy',
        'starts_at' => '2026-11-20 18:00:00',
        'ends_at' => '2026-11-20 20:30:00',
    ]);
});

it('announces every event it finishes, once', function () {
    Event::fake([EventFinished::class]);

    $second = EventModel::factory()->create(['starts_at' => '2026-11-20 17:00:00', 'ends_at' => '2026-11-20 20:00:00']);
    EventModel::factory()->cancelled()->create(['starts_at' => '2026-11-20 17:00:00', 'ends_at' => '2026-11-20 20:00:00']);

    expect(app(FinishEndedEvents::class)->handle())->toBe(2)
        ->and(app(FinishEndedEvents::class)->handle())->toBe(0);

    Event::assertDispatchedTimes(EventFinished::class, 2);
    Event::assertDispatched(EventFinished::class, fn (EventFinished $event) => $event->eventId === $this->event->id);
    Event::assertDispatched(EventFinished::class, fn (EventFinished $event) => $event->eventId === $second->id);
});

it('sends the organizer sold tickets, revenue, refunds and check-ins', function () {
    Mail::fake();

    $paid = TicketFixtures::paidOrder([3], event: $this->event);
    $tickets = TicketFixtures::issueTickets($paid);
    $tickets->take(2)->each(fn ($ticket) => $ticket->forceFill(['status' => TicketStatus::Used, 'checked_in_at' => now()])->save());

    $refunded = TicketFixtures::paidOrder([1], event: $this->event);
    $refunded->forceFill(['status' => 'REFUNDED'])->save();
    Refund::factory()->for($refunded)->completed()->create(['amount' => 5000]);

    $this->event->forceFill(['status' => EventStatus::Finished])->save();

    app(SendPostEventReport::class)->handle(new EventFinished($this->event->id));

    Mail::assertSent(PostEventReportMail::class, function (PostEventReportMail $mail) {
        $mail->assertSeeInHtml('Sprzedane bilety: 3')
            ->assertSeeInHtml('Przychód: 150,00 zł')
            ->assertSeeInHtml('Zwroty: 50,00 zł')
            ->assertSeeInHtml('Wejścia: 2 z 3');

        return $mail->hasTo('org@example.com') && str_contains($mail->envelope()->subject, 'Jazz w Piwnicy');
    });
});

it('sends the report once when the job runs twice', function () {
    Mail::fake();

    $this->event->forceFill(['status' => EventStatus::Finished])->save();

    app(SendPostEventReport::class)->handle(new EventFinished($this->event->id));
    app(SendPostEventReport::class)->handle(new EventFinished($this->event->id));

    Mail::assertSentCount(1);
});

it('sends no report for an event that did not finish', function () {
    Mail::fake();

    $this->event->forceFill(['status' => EventStatus::Cancelled])->save();

    app(SendPostEventReport::class)->handle(new EventFinished($this->event->id));

    Mail::assertNothingSent();
});

it('listens to finished events', function () {
    Event::fake();

    Event::assertListening(EventFinished::class, SendPostEventReport::class);
});

it('sends the report on the notifications queue', function () {
    Queue::fake();

    event(new EventFinished($this->event->id));

    Queue::assertPushedOn(
        QueueName::Notifications->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === SendPostEventReport::class,
    );
});
