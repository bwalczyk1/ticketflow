<?php

use App\Contracts\InvoicePdfGenerator;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Listeners\Documents\IssueInvoice;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Services\Documents\InvoiceNumberAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-20 12:00:00');

    $this->order = TicketFixtures::paidOrder([2]);
});

function lastInvoiceNumber(): ?int
{
    return InvoiceSequence::query()
        ->where('year', 2026)
        ->where('type', InvoiceType::Invoice)
        ->value('last_number');
}

it('issues an invoice with the next number and its PDF', function () {
    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    $invoice = Invoice::query()->where('order_id', $this->order->id)->sole();

    expect($invoice)
        ->type->toBe(InvoiceType::Invoice)
        ->year->toBe(2026)
        ->number->toBe(1)
        ->refund_id->toBeNull()
        ->pdf_path->toBe('invoices/2026/invoice-1.pdf');

    Storage::disk('local')->assertExists('invoices/2026/invoice-1.pdf');
});

it('continues the numbering of the year', function () {
    InvoiceSequence::factory()->create(['year' => 2026, 'type' => InvoiceType::Invoice, 'last_number' => 6]);

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    expect(Invoice::query()->where('order_id', $this->order->id)->value('number'))->toBe(7);
});

it('keeps one invoice and burns no number when it runs twice', function () {
    $listener = app(IssueInvoice::class);

    $listener->handle(new OrderPaid($this->order->id));
    $listener->handle(new OrderPaid($this->order->id));

    expect(Invoice::query()->where('order_id', $this->order->id)->count())->toBe(1)
        ->and(lastInvoiceNumber())->toBe(1);
});

it('checks for the invoice before asking for a number', function () {
    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    $this->mock(InvoiceNumberAllocator::class)->shouldNotReceive('next');

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));
});

it('keeps the number and adds the PDF after an interrupted run', function () {
    $this->mock(InvoicePdfGenerator::class)
        ->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('Disk is full'));

    expect(fn () => app(IssueInvoice::class)->handle(new OrderPaid($this->order->id)))
        ->toThrow(RuntimeException::class, 'Disk is full');

    expect(Invoice::query()->where('order_id', $this->order->id)->sole())
        ->number->toBe(1)
        ->pdf_path->toBeNull();

    $this->app->forgetInstance(InvoicePdfGenerator::class);

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    expect(Invoice::query()->where('order_id', $this->order->id)->sole())
        ->number->toBe(1)
        ->pdf_path->toBe('invoices/2026/invoice-1.pdf')
        ->and(lastInvoiceNumber())->toBe(1);
});

it('gives the number back when saving the invoice fails', function () {
    Invoice::factory()->create(['year' => 2026, 'number' => 1]);

    expect(fn () => app(IssueInvoice::class)->handle(new OrderPaid($this->order->id)))
        ->toThrow(QueryException::class);

    expect(lastInvoiceNumber())->toBeNull()
        ->and(Invoice::query()->where('order_id', $this->order->id)->exists())->toBeFalse();
});

it('issues the invoice of an order refunded in the meantime', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Refunded]);

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    expect(Invoice::query()->where('order_id', $this->order->id)->exists())->toBeTrue();
});

it('issues nothing for an order that was never paid', function (OrderStatus $status) {
    Order::query()->whereKey($this->order->id)->update(['status' => $status]);

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));

    expect(Invoice::query()->exists())->toBeFalse()
        ->and(lastInvoiceNumber())->toBeNull();
})->with([OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled]);

it('does nothing for a missing order', function () {
    app(IssueInvoice::class)->handle(new OrderPaid(999999));

    expect(Invoice::query()->exists())->toBeFalse();
});

it('runs on the documents queue', function () {
    Queue::fake();

    event(new OrderPaid($this->order->id));

    Queue::assertPushedOn(
        QueueName::Documents->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === IssueInvoice::class,
    );
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    app(IssueInvoice::class)->failed(new OrderPaid(7), new RuntimeException('Disk is full'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
