<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Models\Order;
use App\Services\Orders\OrderTransition;

it('moves an order to an allowed status', function () {
    $order = Order::factory()->create();

    expect((new OrderTransition)->apply($order, OrderStatus::Paid))->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->isDirty('status'))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rejects a transition missing from the map', function () {
    $order = Order::factory()->create();

    expect(fn () => (new OrderTransition)->apply($order, OrderStatus::Refunded))
        ->toThrow(InvalidStatusTransition::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('lets only the first of two callers win', function () {
    $order = Order::factory()->create();
    $first = Order::find($order->id);
    $second = Order::find($order->id);
    $transition = new OrderTransition;

    expect($transition->apply($first, OrderStatus::Paid))->toBeTrue()
        ->and($transition->apply($second, OrderStatus::Expired))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('leaves the losing model unchanged', function () {
    $order = Order::factory()->create();
    $stale = Order::find($order->id);
    $transition = new OrderTransition;

    $transition->apply($order, OrderStatus::Expired);
    $transition->apply($stale, OrderStatus::Paid);

    expect($stale->status)->toBe(OrderStatus::Pending);
});

it('reports a repeated identical transition as lost', function () {
    $order = Order::factory()->create();
    $stale = Order::find($order->id);
    $transition = new OrderTransition;

    expect($transition->apply($order, OrderStatus::Failed))->toBeTrue()
        ->and($transition->apply($stale, OrderStatus::Failed))->toBeFalse();
});
