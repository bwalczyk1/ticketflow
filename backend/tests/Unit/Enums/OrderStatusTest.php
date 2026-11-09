<?php

use App\Enums\OrderStatus;

dataset('order transitions', [
    [OrderStatus::Pending, OrderStatus::Paid, true],
    [OrderStatus::Pending, OrderStatus::Expired, true],
    [OrderStatus::Pending, OrderStatus::Failed, true],
    [OrderStatus::Pending, OrderStatus::Cancelled, true],
    [OrderStatus::Pending, OrderStatus::Refunded, false],
    [OrderStatus::Paid, OrderStatus::Refunded, true],
    [OrderStatus::Paid, OrderStatus::Pending, false],
    [OrderStatus::Paid, OrderStatus::Cancelled, false],
    [OrderStatus::Expired, OrderStatus::Paid, false],
    [OrderStatus::Failed, OrderStatus::Paid, false],
    [OrderStatus::Cancelled, OrderStatus::Paid, false],
    [OrderStatus::Refunded, OrderStatus::Paid, false],
]);

it('allows only the mapped transitions', function (OrderStatus $from, OrderStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with('order transitions');

it('knows which statuses still hold seats', function () {
    expect(OrderStatus::Pending->holdsSeats())->toBeTrue()
        ->and(OrderStatus::Paid->holdsSeats())->toBeFalse();
});
