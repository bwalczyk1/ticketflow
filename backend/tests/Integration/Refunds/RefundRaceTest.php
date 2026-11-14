<?php

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Models\Refund;
use App\Services\Refunds\StartRefund;
use Illuminate\Database\Connection;
use Tests\Feature\Tickets\TicketFixtures;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    $this->order = TicketFixtures::paidOrder([2]);
    $this->other = secondConnection();
});

/**
 * The step of a parallel request that refunded the same order first and committed.
 */
function commitRefund(Connection $connection, int $orderId, int $amount): void
{
    $connection->transaction(function () use ($connection, $orderId, $amount) {
        $connection->table('orders')->where('id', $orderId)->update(['status' => OrderStatus::Refunded->value]);

        $connection->table('refunds')->insert([
            'order_id' => $orderId,
            'amount' => $amount,
            'reason' => null,
            'status' => RefundStatus::Pending->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

it('answers ORDER_NOT_REFUNDABLE to the loser of two parallel refunds', function () {
    Interleave::before(Interleave::ORDER_LOCK, fn () => commitRefund($this->other, $this->order->id, $this->order->total));

    expect(fn () => app(StartRefund::class)->forAttendee($this->order, null))->toThrow(OrderNotRefundable::class);

    expect(Refund::query()->count())->toBe(1);
});

it('keeps the order locked until the refund is committed', function () {
    $locked = false;

    Interleave::before('insert into `refunds`', function () use (&$locked) {
        $locked = Interleave::rowIsLocked($this->other, 'orders', $this->order->id);
    });

    app(StartRefund::class)->forAttendee($this->order, null);

    expect($locked)->toBeTrue()
        ->and(Refund::query()->count())->toBe(1);
});
