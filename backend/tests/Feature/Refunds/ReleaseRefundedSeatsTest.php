<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Events\Orders\SeatsReleased;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Orders\ReleaseRefundedSeats;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\TicketType;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Support\Facades\Event as EventBus;
use Tests\Feature\Orders\OrderFixtures;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    EventBus::fake([SeatsReleased::class]);

    $this->event = Event::factory()->create();
    $this->order = TicketFixtures::paidOrder([2, 3], event: $this->event);
    $this->pools = TicketType::query()->where('event_id', $this->event->id)->orderBy('id')->get();

    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Refunded]);

    $this->refunded = new OrderRefunded($this->order->id, Refund::factory()->for($this->order)->create()->id);
});

it('returns the sold seats of a refunded order to the pool once', function () {
    app(ReleaseRefundedSeats::class)->handle($this->refunded);
    app(ReleaseRefundedSeats::class)->handle($this->refunded);

    expect($this->pools->map(fn (TicketType $pool) => $pool->fresh()->only(['sold', 'held', 'version']))->all())->toBe([
        ['sold' => 0, 'held' => 0, 'version' => $this->pools[0]->version + 1],
        ['sold' => 0, 'held' => 0, 'version' => $this->pools[1]->version + 1],
    ])->and($this->order->fresh()->seats_released_at)->not->toBeNull();

    EventBus::assertDispatchedTimes(SeatsReleased::class, 2);
    EventBus::assertDispatched(SeatsReleased::class, fn (SeatsReleased $released) => $released->ticketTypeId === $this->pools[1]->id && $released->quantity === 3);
});

it('keeps the seats of an order that is still paid', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Paid]);

    app(ReleaseRefundedSeats::class)->handle($this->refunded);

    expect($this->pools[0]->fresh()->sold)->toBe(2);

    EventBus::assertNotDispatched(SeatsReleased::class);
});

it('does not offer the seats of a cancelled event to the waitlist', function () {
    Event::query()->whereKey($this->event->id)->update(['status' => EventStatus::Cancelled]);

    app(ReleaseRefundedSeats::class)->handle($this->refunded);

    expect($this->pools[0]->fresh()->sold)->toBe(0);

    EventBus::assertNotDispatched(SeatsReleased::class);
});

it('does not offer held seats of a cancelled event to the waitlist', function () {
    $pending = OrderFixtures::holding($this->pools[0], 1);

    Event::query()->whereKey($this->event->id)->update(['status' => EventStatus::Cancelled]);
    Order::query()->whereKey($pending->id)->update(['status' => OrderStatus::Cancelled]);

    app(ReleaseOrderSeats::class)->handle($pending->id);

    expect($this->pools[0]->fresh()->held)->toBe(0);

    EventBus::assertNotDispatched(SeatsReleased::class);
});
