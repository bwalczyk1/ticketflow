<?php

use App\Enums\OrderStatus;
use App\Events\Orders\OrderPaid;
use App\Models\Order;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00:00');
});

/**
 * A paid order whose OrderPaid got lost, paid the given number of seconds ago.
 */
function orderPaidSecondsAgo(int $seconds): Order
{
    $order = TicketFixtures::paidOrder();

    Order::query()->whereKey($order->id)->update(['updated_at' => now()->subSeconds($seconds)]);

    return $order;
}

it('sends OrderPaid again for a paid order without tickets older than five minutes', function () {
    Event::fake([OrderPaid::class]);

    $lost = orderPaidSecondsAgo(301);

    $this->artisan('ticketflow:reconcile-orders')
        ->expectsOutput('Reconciled 1 orders.')
        ->assertSuccessful();

    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->orderId === $lost->id);
});

it('waits five full minutes before repeating the event', function () {
    Event::fake([OrderPaid::class]);

    orderPaidSecondsAgo(300);

    $this->artisan('ticketflow:reconcile-orders')->assertSuccessful();

    Event::assertNotDispatched(OrderPaid::class);
});

it('skips paid orders that already have tickets', function () {
    Event::fake([OrderPaid::class]);

    TicketFixtures::issueTickets(orderPaidSecondsAgo(600));

    $this->artisan('ticketflow:reconcile-orders')->assertSuccessful();

    Event::assertNotDispatched(OrderPaid::class);
});

it('skips orders that are not paid', function (OrderStatus $status) {
    Event::fake([OrderPaid::class]);

    $order = orderPaidSecondsAgo(600);

    Order::query()->whereKey($order->id)->update(['status' => $status, 'updated_at' => now()->subMinutes(10)]);

    $this->artisan('ticketflow:reconcile-orders')->assertSuccessful();

    Event::assertNotDispatched(OrderPaid::class);
})->with([OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled, OrderStatus::Refunded]);

it('repairs a lost order once, so the next run finds nothing', function () {
    Storage::fake('local');
    Mail::fake();

    $order = orderPaidSecondsAgo(600);

    $this->artisan('ticketflow:reconcile-orders')->expectsOutput('Reconciled 1 orders.');

    expect(TicketFixtures::ticketsOf($order))->toHaveCount(2);

    $this->artisan('ticketflow:reconcile-orders')->expectsOutput('Reconciled 0 orders.');
});

it('runs every five minutes', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->filter(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'ticketflow:reconcile-orders'));

    expect($scheduled)->toHaveCount(1)
        ->and($scheduled->first()->expression)->toBe('*/5 * * * *');
});
