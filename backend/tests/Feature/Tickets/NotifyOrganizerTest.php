<?php

use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Listeners\Notifications\NotifyOrganizer;
use App\Mail\OrganizerOrderMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\SentNotification;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Mail::fake();

    $organizer = User::factory()->organizer()->create(['name' => 'Olivia Organizer', 'email' => 'olivia@example.com']);

    $this->event = Event::factory()->for($organizer, 'organizer')->create(['title' => 'Koncert Żółć']);
    $this->order = TicketFixtures::paidOrder([2, 1], event: $this->event);
});

it('tells the organizer about the paid order', function () {
    app(NotifyOrganizer::class)->handle(new OrderPaid($this->order->id));

    Mail::assertSent(OrganizerOrderMail::class, fn (OrganizerOrderMail $mail) => $mail->hasTo('olivia@example.com'));
});

it('notifies once when it runs twice', function () {
    $listener = app(NotifyOrganizer::class);

    $listener->handle(new OrderPaid($this->order->id));
    $listener->handle(new OrderPaid($this->order->id));

    Mail::assertSentCount(1);

    expect(SentNotification::query()->where('key', "order:{$this->order->id}:organizer")->count())->toBe(1);
});

it('writes in Polish with the ticket count and the amount', function () {
    $order = Order::query()
        ->with(['event:id,organizer_id,title', 'event.organizer:id,name,email', 'items:id,order_id,quantity'])
        ->find($this->order->id, ['id', 'event_id', 'total']);

    $mail = new OrganizerOrderMail($order);

    $mail->assertHasSubject('Nowe zamówienie: Koncert Żółć');
    $mail->assertSeeInHtml('Olivia Organizer');
    $mail->assertSeeInHtml('Liczba biletów: 3');
    $mail->assertSeeInHtml('150,00 zł');
});

it('does nothing for a missing order', function () {
    app(NotifyOrganizer::class)->handle(new OrderPaid(999999));

    Mail::assertNothingSent();
});

it('runs on the notifications queue', function () {
    Queue::fake();

    event(new OrderPaid($this->order->id));

    Queue::assertPushedOn(
        QueueName::Notifications->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === NotifyOrganizer::class,
    );
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    app(NotifyOrganizer::class)->failed(new OrderPaid(7), new RuntimeException('SMTP is down'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
