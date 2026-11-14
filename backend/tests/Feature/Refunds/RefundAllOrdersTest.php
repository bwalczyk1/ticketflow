<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Events\Orders\SeatsReleased;
use App\Events\Refunds\OrderRefunded;
use App\Jobs\Refunds\RefundOrder;
use App\Listeners\Refunds\RefundAllOrders;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TicketType;
use App\Services\Refunds\StartRefund;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event as EventBus;
use Tests\Feature\Orders\OrderFixtures;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    // An hour before the start: far past the 48 hour deadline, which a cancellation ignores.
    $this->travelTo('2026-12-01 17:00:00');

    $this->event = Event::factory()->cancelled()->create(['starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);
    $this->paid = [TicketFixtures::paidOrder([2], event: $this->event), TicketFixtures::paidOrder([1], event: $this->event)];
    $this->pool = TicketType::factory()->for($this->event)->create(['quantity' => 10]);
    $this->pending = OrderFixtures::holding($this->pool, 3);
    $this->cancelled = new EventCancelled($this->event->id);
});

it('refunds every paid order of the event in one named batch on the refunds queue', function () {
    Bus::fake();

    Order::factory()->paid()->create();
    TicketFixtures::paidOrder([1], event: $this->event)->update(['status' => OrderStatus::Refunded]);

    app(RefundAllOrders::class)->handle($this->cancelled);

    Bus::assertBatched(function (PendingBatch $batch) {
        $orderIds = $batch->jobs->map(fn (RefundOrder $job) => $job->orderId)->all();

        return $batch->name === "Refunds of cancelled event #{$this->event->id}"
            && $batch->queue() === QueueName::Refunds->value
            && $orderIds === [$this->paid[0]->id, $this->paid[1]->id];
    });
});

it('cancels pending orders of the event and gives their seats back without the waitlist', function () {
    Bus::fake();
    EventBus::fake([SeatsReleased::class]);

    Payment::factory()->for($this->pending)->create();

    app(RefundAllOrders::class)->handle($this->cancelled);

    expect($this->pending->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->pool->fresh()->held)->toBe(0);

    EventBus::assertNotDispatched(SeatsReleased::class);
});

it('dispatches no batch for an event without paid orders', function () {
    Bus::fake();

    Order::query()->whereIn('id', [$this->paid[0]->id, $this->paid[1]->id])->update(['status' => OrderStatus::Refunded]);

    app(RefundAllOrders::class)->handle($this->cancelled);

    Bus::assertNothingBatched();
});

it('refunds the order of a batch job without the 48 hour rule and with the cancellation reason', function () {
    EventBus::fake([OrderRefunded::class]);

    app()->call([new RefundOrder($this->paid[0]->id), 'handle']);

    expect($this->paid[0]->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Refund::query()->sole())
        ->order_id->toBe($this->paid[0]->id)
        ->amount->toBe($this->paid[0]->total)
        ->reason->toBe(StartRefund::EVENT_CANCELLED_REASON);

    EventBus::assertDispatched(OrderRefunded::class);
});

it('skips an order that is already refunded when a job runs twice', function () {
    EventBus::fake([OrderRefunded::class]);

    app()->call([new RefundOrder($this->paid[0]->id), 'handle']);
    app()->call([new RefundOrder($this->paid[0]->id), 'handle']);

    expect(Refund::query()->count())->toBe(1);

    EventBus::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('refunds the whole event once the batch runs', function () {
    EventBus::fake([OrderRefunded::class, SeatsReleased::class]);

    app(RefundAllOrders::class)->handle($this->cancelled);
    app(RefundAllOrders::class)->handle($this->cancelled);

    expect(collect($this->paid)->map(fn (Order $order) => $order->fresh()->status)->all())->toBe([OrderStatus::Refunded, OrderStatus::Refunded])
        ->and(Refund::query()->count())->toBe(2)
        ->and($this->event->fresh()->status)->toBe(EventStatus::Cancelled);

    EventBus::assertDispatchedTimes(OrderRefunded::class, 2);
});
