<?php

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Jobs\Documents\GenerateTicketPdfs;
use App\Listeners\Tickets\IssueTickets;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Bus::fake([GenerateTicketPdfs::class]);
});

it('issues one valid ticket with a UUID code for every seat', function () {
    $order = TicketFixtures::paidOrder([2, 1]);

    app(IssueTickets::class)->handle(new OrderPaid($order->id));

    $tickets = TicketFixtures::ticketsOf($order);

    expect($tickets)->toHaveCount(3)
        ->and($tickets->pluck('position')->all())->toBe([1, 2, 1])
        ->and($tickets->pluck('status')->unique()->all())->toBe([TicketStatus::Valid])
        ->and($tickets->pluck('code')->unique())->toHaveCount(3)
        ->and($tickets->every(fn (Ticket $ticket) => Str::isUuid($ticket->code)))->toBeTrue();
});

it('keeps one set of tickets when it runs twice', function () {
    $order = TicketFixtures::paidOrder([2, 1]);
    $listener = app(IssueTickets::class);

    $listener->handle(new OrderPaid($order->id));

    $codes = TicketFixtures::ticketsOf($order)->pluck('code')->all();

    $listener->handle(new OrderPaid($order->id));

    expect(TicketFixtures::ticketsOf($order)->pluck('code')->all())->toBe($codes);
});

it('adds only the missing tickets after an interrupted run', function () {
    $order = TicketFixtures::paidOrder([3]);
    $item = $order->items()->first();
    $issued = Ticket::factory()->for($item)->create(['position' => 1]);

    app(IssueTickets::class)->handle(new OrderPaid($order->id));

    $tickets = TicketFixtures::ticketsOf($order);

    expect($tickets->pluck('position')->all())->toBe([1, 2, 3])
        ->and($tickets->first()->code)->toBe($issued->code);
});

it('hands the order on to PDF generation on the documents queue', function () {
    $order = TicketFixtures::paidOrder();

    app(IssueTickets::class)->handle(new OrderPaid($order->id));

    Bus::assertDispatched(
        GenerateTicketPdfs::class,
        fn (GenerateTicketPdfs $job) => $job->orderId === $order->id && $job->queue === QueueName::Documents->value,
    );
});

it('hands the order on again when retried, so the next step is never lost', function () {
    $order = TicketFixtures::paidOrder();
    $listener = app(IssueTickets::class);

    $listener->handle(new OrderPaid($order->id));
    $listener->handle(new OrderPaid($order->id));

    Bus::assertDispatchedTimes(GenerateTicketPdfs::class, 2);
});

it('issues nothing for an order that is not paid', function (OrderStatus $status) {
    $order = TicketFixtures::paidOrder();

    Order::query()->whereKey($order->id)->update(['status' => $status]);

    app(IssueTickets::class)->handle(new OrderPaid($order->id));

    expect(TicketFixtures::ticketsOf($order))->toBeEmpty();

    Bus::assertNotDispatched(GenerateTicketPdfs::class);
})->with([OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled, OrderStatus::Refunded]);

it('does nothing for a missing order', function () {
    app(IssueTickets::class)->handle(new OrderPaid(999999));

    expect(Ticket::query()->count())->toBe(0);

    Bus::assertNotDispatched(GenerateTicketPdfs::class);
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    app(IssueTickets::class)->failed(new OrderPaid(7), new RuntimeException('Database is down'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
