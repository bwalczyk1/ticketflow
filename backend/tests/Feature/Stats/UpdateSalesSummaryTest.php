<?php

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Refunds\OrderRefunded;
use App\Events\Tickets\TicketCheckedIn;
use App\Listeners\Stats\UpdateSalesSummary;
use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->event = Event::factory()->create();
});

/**
 * @return array{tickets_sold: int, revenue: int, refunds: int, checked_in: int}
 */
function salesSummaryOf(Event $event): array
{
    return EventSalesSummary::query()
        ->whereKey($event->id)
        ->firstOrFail(['tickets_sold', 'revenue', 'refunds', 'checked_in'])
        ->only(['tickets_sold', 'revenue', 'refunds', 'checked_in']);
}

it('sums the paid orders of the event', function () {
    $first = TicketFixtures::paidOrder([2], event: $this->event);

    TicketFixtures::paidOrder([1, 2], event: $this->event);

    app(UpdateSalesSummary::class)->handle(new OrderPaid($first->id));

    expect(salesSummaryOf($this->event))->toBe([
        'tickets_sold' => 5,
        'revenue' => 5 * TicketFixtures::PRICE,
        'refunds' => 0,
        'checked_in' => 0,
    ]);
});

it('leaves out orders that are not paid and other events', function (OrderStatus $status) {
    $paid = TicketFixtures::paidOrder([1], event: $this->event);
    $other = TicketFixtures::paidOrder([3], event: $this->event);

    Order::query()->whereKey($other->id)->update(['status' => $status]);
    TicketFixtures::paidOrder([4]);

    app(UpdateSalesSummary::class)->handle(new OrderPaid($paid->id));

    expect(salesSummaryOf($this->event))
        ->tickets_sold->toBe(1)
        ->revenue->toBe(TicketFixtures::PRICE);
})->with([OrderStatus::Pending, OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled]);

it('gives the same summary when it runs twice', function () {
    $order = TicketFixtures::paidOrder([2], event: $this->event);
    $listener = app(UpdateSalesSummary::class);

    $listener->handle(new OrderPaid($order->id));

    $once = salesSummaryOf($this->event);

    $listener->handle(new OrderPaid($order->id));

    expect(salesSummaryOf($this->event))->toBe($once)
        ->and(EventSalesSummary::query()->count())->toBe(1);
});

it('replaces a summary that drifted from the orders', function () {
    $order = TicketFixtures::paidOrder([2], event: $this->event);

    EventSalesSummary::factory()->for($this->event)->create(['tickets_sold' => 40, 'revenue' => 1, 'checked_in' => 9]);

    app(UpdateSalesSummary::class)->handle(new OrderPaid($order->id));

    expect(salesSummaryOf($this->event))
        ->tickets_sold->toBe(2)
        ->revenue->toBe(2 * TicketFixtures::PRICE)
        ->checked_in->toBe(0);
});

it('counts checked-in tickets after a check-in', function () {
    $order = TicketFixtures::paidOrder([3], event: $this->event);
    $tickets = TicketFixtures::issueTickets($order);

    $tickets[0]->update(['status' => TicketStatus::Used, 'checked_in_at' => now()]);
    $tickets[1]->update(['status' => TicketStatus::Used, 'checked_in_at' => now()]);

    app(UpdateSalesSummary::class)->handle(new TicketCheckedIn($tickets[0]->id));

    expect(salesSummaryOf($this->event))
        ->tickets_sold->toBe(3)
        ->checked_in->toBe(2);
});

it('moves a refunded order from revenue to refunds', function () {
    $kept = TicketFixtures::paidOrder([1], event: $this->event);
    $refunded = TicketFixtures::paidOrder([2], event: $this->event);

    Order::query()->whereKey($refunded->id)->update(['status' => OrderStatus::Refunded]);

    $refund = Refund::factory()->for($refunded)->create(['amount' => $refunded->total, 'status' => RefundStatus::Completed]);

    app(UpdateSalesSummary::class)->handle(new OrderRefunded($refunded->id, $refund->id));

    expect(salesSummaryOf($this->event))->toBe([
        'tickets_sold' => 1,
        'revenue' => $kept->total,
        'refunds' => $refunded->total,
        'checked_in' => 0,
    ]);
});

it('does not count a failed refund', function () {
    $order = TicketFixtures::paidOrder([2], event: $this->event);
    $refund = Refund::factory()->for($order)->create(['amount' => $order->total, 'status' => RefundStatus::Failed]);

    app(UpdateSalesSummary::class)->handle(new OrderRefunded($order->id, $refund->id));

    expect(salesSummaryOf($this->event)['refunds'])->toBe(0);
});

it('does nothing for a missing order or ticket', function (object $event) {
    app(UpdateSalesSummary::class)->handle($event);

    expect(EventSalesSummary::query()->count())->toBe(0);
})->with([
    'order' => fn () => new OrderPaid(999999),
    'ticket' => fn () => new TicketCheckedIn(999999),
]);

it('runs on the stats queue for every event it listens to', function (object $event) {
    Queue::fake();

    event($event);

    Queue::assertPushedOn(
        QueueName::Stats->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === UpdateSalesSummary::class,
    );
})->with([
    'paid order' => fn () => new OrderPaid(1),
    'refunded order' => fn () => new OrderRefunded(1, 1),
    'checked-in ticket' => fn () => new TicketCheckedIn(1),
]);

it('logs the event when all attempts are used up', function () {
    Log::spy();

    app(UpdateSalesSummary::class)->failed(new OrderPaid(7), new RuntimeException('Database is down'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['event'] === OrderPaid::class);
});
