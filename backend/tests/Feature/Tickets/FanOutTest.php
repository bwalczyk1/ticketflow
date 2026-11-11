<?php

use App\Contracts\PaymentResultHandler;
use App\Enums\QueueName;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Refunds\OrderRefunded;
use App\Events\Tickets\TicketCheckedIn;
use App\Events\Tickets\TicketsGenerated;
use App\Listeners\Documents\IssueInvoice;
use App\Listeners\Notifications\NotifyOrganizer;
use App\Listeners\Stats\UpdateSalesSummary;
use App\Listeners\Tickets\IssueTickets;
use App\Listeners\Tickets\SendTicketsEmail;
use App\Mail\OrganizerOrderMail;
use App\Mail\TicketsMail;
use App\Models\EventSalesSummary;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Orders\OrderFixtures;

/**
 * Consumers and their queues from the event table of the plan.
 *
 * @return array<string, array{0: class-string, 1: class-string, 2: QueueName}>
 */
dataset('consumers', [
    'IssueTickets' => [OrderPaid::class, IssueTickets::class, QueueName::Tickets],
    'IssueInvoice' => [OrderPaid::class, IssueInvoice::class, QueueName::Documents],
    'UpdateSalesSummary after a payment' => [OrderPaid::class, UpdateSalesSummary::class, QueueName::Stats],
    'NotifyOrganizer' => [OrderPaid::class, NotifyOrganizer::class, QueueName::Notifications],
    'SendTicketsEmail' => [TicketsGenerated::class, SendTicketsEmail::class, QueueName::Mail],
    'UpdateSalesSummary after a refund' => [OrderRefunded::class, UpdateSalesSummary::class, QueueName::Stats],
    'UpdateSalesSummary after a check-in' => [TicketCheckedIn::class, UpdateSalesSummary::class, QueueName::Stats],
]);

/**
 * @param  class-string  $event
 */
function fanOutEvent(string $event): object
{
    return match ($event) {
        OrderRefunded::class => new OrderRefunded(1, 1),
        TicketCheckedIn::class => new TicketCheckedIn(1),
        default => new $event(1),
    };
}

it('registers every consumer of the event', function (string $event, string $listener) {
    Event::fake();

    Event::assertListening($event, $listener);
})->with('consumers');

it('puts every consumer on its own queue', function (string $event, string $listener, QueueName $queue) {
    Queue::fake();

    event(fanOutEvent($event));

    Queue::assertPushedOn(
        $queue->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === $listener,
    );
})->with('consumers');

it('turns one payment into tickets, PDFs, an invoice, e-mails and statistics', function () {
    Storage::fake('local');
    Mail::fake();

    $pool = TicketType::factory()->create(['quantity' => 10, 'price' => 6000]);
    $order = OrderFixtures::holding($pool, 2, attributes: ['total' => 12000]);
    $payment = Payment::factory()->for($order)->create(['amount' => 12000]);

    app(PaymentResultHandler::class)->succeeded($payment, 'ref_1');

    $tickets = Ticket::query()->whereHas('orderItem', fn ($items) => $items->where('order_id', $order->id))->get();
    $invoice = Invoice::query()->where('order_id', $order->id)->sole();

    expect($tickets)->toHaveCount(2)
        ->and($tickets->every(fn (Ticket $ticket) => $ticket->status === TicketStatus::Valid))->toBeTrue()
        ->and($invoice->pdf_path)->not->toBeNull()
        ->and(EventSalesSummary::query()->whereKey($order->event_id)->value('tickets_sold'))->toBe(2);

    foreach ($tickets as $ticket) {
        Storage::disk('local')->assertExists($ticket->pdf_path);
    }

    Storage::disk('local')->assertExists($invoice->pdf_path);

    Mail::assertSent(TicketsMail::class, fn (TicketsMail $mail) => count($mail->attachments()) === 2);
    Mail::assertSent(OrganizerOrderMail::class);
});
