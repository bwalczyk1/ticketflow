<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Jobs\Notifications\SendEventReminder;
use App\Mail\EventReminderMail;
use App\Models\Event as EventModel;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationLedger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->travelTo('2026-11-19 18:00:00');

    // 19:30 in Warsaw on 20 November, 24.5 hours from now.
    $this->event = EventModel::factory()->create([
        'title' => 'Jazz w Piwnicy',
        'starts_at' => '2026-11-20 18:30:00',
        'ends_at' => '2026-11-20 21:00:00',
    ]);
});

function eventAt(string $startsAt, array $attributes = []): EventModel
{
    $startsAt = CarbonImmutable::parse($startsAt);

    return EventModel::factory()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHours(3), ...$attributes]);
}

it('queues one reminder for every attendee with paid tickets', function () {
    Queue::fake();

    $twice = User::factory()->create();
    $once = User::factory()->create();

    TicketFixtures::paidOrder([1], $twice, $this->event);
    TicketFixtures::paidOrder([2], $twice, $this->event);
    TicketFixtures::paidOrder([1], $once, $this->event);
    Order::factory()->create(['event_id' => $this->event->id]);
    Order::factory()->refunded()->create(['event_id' => $this->event->id]);

    $this->artisan('ticketflow:send-event-reminders')
        ->expectsOutput('Sent reminders for 1 events.')
        ->assertSuccessful();

    Queue::assertPushedOn(QueueName::Mail->value, SendEventReminder::class);
    Queue::assertPushed(SendEventReminder::class, 2);
    Queue::assertPushed(SendEventReminder::class, fn (SendEventReminder $job) => $job->eventId === $this->event->id && $job->userId === $twice->id);
    Queue::assertPushed(SendEventReminder::class, fn (SendEventReminder $job) => $job->userId === $once->id);

    expect($this->event->fresh()->reminder_sent_at->toDateTimeString())->toBe('2026-11-19 18:00:00');
});

it('reminds about an event only once', function () {
    Queue::fake();

    TicketFixtures::paidOrder([1], event: $this->event);

    $this->artisan('ticketflow:send-event-reminders')->assertSuccessful();

    $this->travel(30)->minutes();

    $this->artisan('ticketflow:send-event-reminders')
        ->expectsOutput('Sent reminders for 0 events.')
        ->assertSuccessful();

    Queue::assertPushed(SendEventReminder::class, 1);
});

it('skips events outside the window, not published or already reminded', function (Closure $event) {
    Queue::fake();

    TicketFixtures::paidOrder([1], event: $event());

    $this->event->forceFill(['reminder_sent_at' => now()])->save();

    $this->artisan('ticketflow:send-event-reminders')->assertSuccessful();

    Queue::assertNothingPushed();
})->with([
    'in 23 hours' => [fn () => eventAt('2026-11-20 17:00:00')],
    'in 25 hours' => [fn () => eventAt('2026-11-20 19:00:00')],
    'cancelled' => [fn () => eventAt('2026-11-20 18:30:00', ['status' => EventStatus::Cancelled])],
    'draft' => [fn () => eventAt('2026-11-20 18:30:00', ['status' => EventStatus::Draft])],
    'already reminded' => [fn () => test()->event],
]);

it('sends the reminder with the local start time of the event', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'anna@example.com']);

    TicketFixtures::paidOrder([2], $user, $this->event);
    TicketFixtures::paidOrder([1], $user, $this->event);

    (new SendEventReminder($this->event->id, $user->id))->handle(app(NotificationLedger::class));

    Mail::assertSent(EventReminderMail::class, function (EventReminderMail $mail) {
        $mail->assertSeeInHtml('20.11.2026 19:30')
            ->assertSeeInHtml('Jazz w Piwnicy')
            ->assertSeeInHtml('3');

        return $mail->hasTo('anna@example.com') && str_contains($mail->envelope()->subject, 'Jazz w Piwnicy');
    });
});

it('sends a reminder once when the job runs twice', function () {
    Mail::fake();

    $user = User::factory()->create();

    TicketFixtures::paidOrder([1], $user, $this->event);

    $job = new SendEventReminder($this->event->id, $user->id);

    $job->handle(app(NotificationLedger::class));
    $job->handle(app(NotificationLedger::class));

    Mail::assertSentCount(1);
});

it('sends no reminder when the tickets or the event are gone meanwhile', function (Closure $change) {
    Mail::fake();

    $user = User::factory()->create();
    $order = TicketFixtures::paidOrder([1], $user, $this->event);

    $change($order, $this->event);

    (new SendEventReminder($this->event->id, $user->id))->handle(app(NotificationLedger::class));

    Mail::assertNothingSent();
})->with([
    'refunded' => [fn (Order $order) => $order->forceFill(['status' => OrderStatus::Refunded])->save()],
    'cancelled event' => [fn (Order $order, EventModel $event) => $event->forceFill(['status' => EventStatus::Cancelled])->save()],
]);

it('runs every hour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'ticketflow:send-event-reminders'));

    expect($event?->expression)->toBe('0 * * * *');
});
