<?php

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Tickets\TicketsGenerated;
use App\Listeners\Tickets\SendTicketsEmail;
use App\Mail\TicketsMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\SentNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();

    $this->buyer = User::factory()->create(['name' => 'Zażółć Gęślą', 'email' => 'buyer@example.com']);

    $this->event = Event::factory()->create([
        'title' => 'Koncert Żółć',
        'timezone' => 'Europe/Warsaw',
        'starts_at' => '2026-12-01 18:00:00',
        'ends_at' => '2026-12-01 21:00:00',
    ]);

    $this->order = TicketFixtures::paidOrder([2], $this->buyer, $this->event);
    $this->tickets = TicketFixtures::issueTickets($this->order);

    foreach ($this->tickets as $ticket) {
        $ticket->update(['pdf_path' => "tickets/{$ticket->code}.pdf"]);

        Storage::disk('local')->put("tickets/{$ticket->code}.pdf", '%PDF-1.7 fake');
    }
});

it('sends the buyer one e-mail with a PDF for every ticket', function () {
    app(SendTicketsEmail::class)->handle(new TicketsGenerated($this->order->id));

    Mail::assertSent(TicketsMail::class, function (TicketsMail $mail) {
        $attached = $this->tickets->every(fn (Ticket $ticket) => $mail->hasAttachment(
            Attachment::fromStorageDisk('local', "tickets/{$ticket->code}.pdf")
                ->as("bilet-{$ticket->code}.pdf")
                ->withMime('application/pdf'),
        ));

        return $mail->hasTo('buyer@example.com') && count($mail->attachments()) === 2 && $attached;
    });
});

it('sends once when it runs twice', function () {
    $listener = app(SendTicketsEmail::class);

    $listener->handle(new TicketsGenerated($this->order->id));
    $listener->handle(new TicketsGenerated($this->order->id));

    Mail::assertSentCount(1);

    expect(SentNotification::query()->where('key', "order:{$this->order->id}:tickets")->count())->toBe(1);
});

it('sends after a retry when a PDF was not ready', function () {
    $this->tickets->last()->update(['pdf_path' => null]);

    expect(fn () => app(SendTicketsEmail::class)->handle(new TicketsGenerated($this->order->id)))
        ->toThrow(RuntimeException::class);

    Mail::assertNothingSent();

    expect(SentNotification::query()->count())->toBe(0);

    $this->tickets->last()->update(['pdf_path' => "tickets/{$this->tickets->last()->code}.pdf"]);

    app(SendTicketsEmail::class)->handle(new TicketsGenerated($this->order->id));

    Mail::assertSentCount(1);
});

it('sends nothing for an order that is no longer paid', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Refunded]);

    app(SendTicketsEmail::class)->handle(new TicketsGenerated($this->order->id));

    Mail::assertNothingSent();

    expect(SentNotification::query()->count())->toBe(0);
});

it('writes the e-mail in Polish with the local start time', function () {
    $order = Order::query()
        ->with(['user:id,name,email', 'event:id,title,venue,city,timezone,starts_at'])
        ->find($this->order->id, ['id', 'user_id', 'event_id']);

    $mail = new TicketsMail($order, $this->tickets);

    $mail->assertHasSubject('Twoje bilety: Koncert Żółć');
    $mail->assertSeeInHtml('Zażółć Gęślą');
    $mail->assertSeeInHtml('01.12.2026 19:00');
    $mail->assertSeeInHtml('Europe/Warsaw');
});

it('runs on the mail queue', function () {
    Queue::fake();

    event(new TicketsGenerated($this->order->id));

    Queue::assertPushedOn(
        QueueName::Mail->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === SendTicketsEmail::class,
    );
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    app(SendTicketsEmail::class)->failed(new TicketsGenerated(7), new RuntimeException('SMTP is down'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
