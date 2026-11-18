<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidStatusTransition;
use App\Models\Order;

it('moves an order to an allowed status', function () {
    $order = Order::factory()->create();

    $order->transitionTo(OrderStatus::Paid);

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->isDirty('status'))->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('rejects a transition missing from the map', function () {
    $order = Order::factory()->create();

    expect(fn () => $order->transitionTo(OrderStatus::Refunded))
        ->toThrow(InvalidStatusTransition::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects a second transition out of a final status', function () {
    $order = Order::factory()->create();

    $order->transitionTo(OrderStatus::Failed);

    expect(fn () => $order->transitionTo(OrderStatus::Failed))
        ->toThrow(InvalidStatusTransition::class);
});
