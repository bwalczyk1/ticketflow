<?php

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Orders\ReservationExpired;
use App\Events\Orders\SeatsReleased;
use App\Events\Payments\PaymentFailed;
use App\Listeners\Orders\ReleaseSeats;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\Orders\ReleaseOrderSeats;
use Illuminate\Database\QueryException;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
});

it('gives the held seats back and records the release', function (OrderStatus $status) {
    Event::fake([SeatsReleased::class]);
    $this->freezeTime();

    $order = OrderFixtures::holding($this->pool, 3, attributes: ['status' => $status]);

    app(ReleaseOrderSeats::class)->handle($order->id);

    expect($this->pool->fresh())->held->toBe(0)->version->toBe(1)
        ->and($order->fresh()->seats_released_at->toDateTimeString())->toBe(now()->toDateTimeString());

    Event::assertDispatched(
        SeatsReleased::class,
        fn (SeatsReleased $event) => $event->ticketTypeId === $this->pool->id && $event->quantity === 3,
    );
})->with([OrderStatus::Expired, OrderStatus::Failed, OrderStatus::Cancelled]);

it('releases every pool of the order', function () {
    Event::fake([SeatsReleased::class]);

    $second = TicketType::factory()->create(['event_id' => $this->pool->event_id]);
    $order = OrderFixtures::holding($this->pool, 2, attributes: ['status' => OrderStatus::Expired]);

    OrderItem::factory()->for($order)->create(['ticket_type_id' => $second->id, 'quantity' => 1]);
    TicketType::query()->whereKey($second->id)->increment('held', 1);

    app(ReleaseOrderSeats::class)->handle($order->id);

    expect($this->pool->fresh()->held)->toBe(0)
        ->and($second->fresh()->held)->toBe(0);

    Event::assertDispatchedTimes(SeatsReleased::class, 2);
});

it('releases only once when it runs twice', function () {
    Event::fake([SeatsReleased::class]);

    $other = OrderFixtures::holding($this->pool, 1);
    $order = OrderFixtures::holding($this->pool, 3, attributes: ['status' => OrderStatus::Failed]);

    app(ReleaseOrderSeats::class)->handle($order->id);
    app(ReleaseOrderSeats::class)->handle($order->id);

    expect($this->pool->fresh()->held)->toBe(1)
        ->and($other->fresh()->seats_released_at)->toBeNull();

    Event::assertDispatchedTimes(SeatsReleased::class, 1);
});

it('leaves seats of orders that still own them', function (OrderStatus $status) {
    Event::fake([SeatsReleased::class]);

    $order = OrderFixtures::holding($this->pool, 3, attributes: ['status' => $status]);

    app(ReleaseOrderSeats::class)->handle($order->id);

    expect($this->pool->fresh()->held)->toBe(3)
        ->and($order->fresh()->seats_released_at)->toBeNull();

    Event::assertNotDispatched(SeatsReleased::class);
})->with([OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Refunded]);

it('can be retried after a failure in the middle', function () {
    Event::fake([SeatsReleased::class]);

    $order = OrderFixtures::holding($this->pool, 3, attributes: ['status' => OrderStatus::Failed]);

    TicketType::query()->whereKey($this->pool->id)->update(['held' => 1]);

    expect(fn () => app(ReleaseOrderSeats::class)->handle($order->id))->toThrow(QueryException::class);

    expect($order->fresh()->seats_released_at)->toBeNull();

    Event::assertNotDispatched(SeatsReleased::class);

    TicketType::query()->whereKey($this->pool->id)->update(['held' => 3]);

    app(ReleaseOrderSeats::class)->handle($order->id);

    expect($this->pool->fresh()->held)->toBe(0)
        ->and($order->fresh()->seats_released_at)->not->toBeNull();
});

it('compensates once when both a failed payment and an expiry arrive', function () {
    $order = OrderFixtures::holding($this->pool, 3, attributes: ['status' => OrderStatus::Failed]);

    event(new PaymentFailed($order->id, 'Card declined'));
    event(new ReservationExpired($order->id));

    expect($this->pool->fresh()->held)->toBe(0);
});

it('listens to the failed payment and the expired reservation', function () {
    Event::fake();

    Event::assertListening(PaymentFailed::class, ReleaseSeats::class);
    Event::assertListening(ReservationExpired::class, ReleaseSeats::class);
});

it('runs on the orders queue', function (object $event) {
    Queue::fake();

    event($event);

    Queue::assertPushedOn(
        QueueName::Orders->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === ReleaseSeats::class,
    );
})->with([
    'failed payment' => fn () => new PaymentFailed(1, 'Card declined'),
    'expired reservation' => fn () => new ReservationExpired(1),
]);

it('does nothing for a missing order', function () {
    Event::fake([SeatsReleased::class]);

    app(ReleaseOrderSeats::class)->handle(999999);

    Event::assertNotDispatched(SeatsReleased::class);
});
