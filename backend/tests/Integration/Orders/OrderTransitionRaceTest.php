<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderTransition;
use Illuminate\Support\Facades\DB;

it('loses to a commit from another connection even inside a repeatable read snapshot', function () {
    $order = Order::factory()->create();
    $other = secondConnection();

    DB::transaction(function () use ($order, $other) {
        $stale = Order::find($order->id);

        $other->table('orders')->where('id', $order->id)->update(['status' => OrderStatus::Expired->value]);

        expect(Order::find($order->id)->status)->toBe(OrderStatus::Pending)
            ->and((new OrderTransition)->apply($stale, OrderStatus::Paid))->toBeFalse();
    });

    expect($order->fresh()->status)->toBe(OrderStatus::Expired);
});
