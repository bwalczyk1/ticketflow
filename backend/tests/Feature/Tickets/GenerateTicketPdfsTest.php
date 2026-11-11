<?php

use App\Contracts\TicketPdfGenerator;
use App\Enums\QueueName;
use App\Events\Tickets\TicketsGenerated;
use App\Jobs\Documents\GenerateTicketPdfs;
use App\Models\Ticket;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    Event::fake([TicketsGenerated::class]);

    $this->order = TicketFixtures::paidOrder([2]);
    $this->tickets = TicketFixtures::issueTickets($this->order);
});

function runGenerateTicketPdfs(int $orderId): void
{
    app()->call([new GenerateTicketPdfs($orderId), 'handle']);
}

it('writes a PDF for every ticket and records its path', function () {
    runGenerateTicketPdfs($this->order->id);

    foreach ($this->tickets as $ticket) {
        expect($ticket->fresh()->pdf_path)->toBe("tickets/{$ticket->code}.pdf");

        Storage::disk('local')->assertExists("tickets/{$ticket->code}.pdf");
    }
});

it('announces the generated tickets', function () {
    runGenerateTicketPdfs($this->order->id);

    Event::assertDispatched(TicketsGenerated::class, fn (TicketsGenerated $event) => $event->orderId === $this->order->id);
});

it('does not generate a PDF twice but announces again when retried', function () {
    $generator = $this->mock(TicketPdfGenerator::class);

    $generator->shouldReceive('generate')
        ->twice()
        ->andReturnUsing(fn (Ticket $ticket) => "tickets/{$ticket->code}.pdf");

    runGenerateTicketPdfs($this->order->id);
    runGenerateTicketPdfs($this->order->id);

    Event::assertDispatchedTimes(TicketsGenerated::class, 2);
});

it('finishes the remaining PDFs after an interrupted run', function () {
    $generator = $this->mock(TicketPdfGenerator::class);

    $generator->shouldReceive('generate')
        ->once()
        ->andReturnUsing(fn (Ticket $ticket) => "tickets/{$ticket->code}.pdf");

    $generator->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('Disk is full'));

    expect(fn () => runGenerateTicketPdfs($this->order->id))->toThrow(RuntimeException::class, 'Disk is full');

    Event::assertNotDispatched(TicketsGenerated::class);

    $this->app->forgetInstance(TicketPdfGenerator::class);

    runGenerateTicketPdfs($this->order->id);

    expect(TicketFixtures::ticketsOf($this->order)->pluck('pdf_path')->filter())->toHaveCount(2);

    Event::assertDispatchedTimes(TicketsGenerated::class, 1);
});

it('announces nothing for an order without tickets', function () {
    $order = TicketFixtures::paidOrder();

    runGenerateTicketPdfs($order->id);

    Event::assertNotDispatched(TicketsGenerated::class);
});

it('runs on the documents queue', function () {
    expect((new GenerateTicketPdfs(1))->queue)->toBe(QueueName::Documents->value);
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    (new GenerateTicketPdfs(7))->failed(new RuntimeException('Disk is full'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
