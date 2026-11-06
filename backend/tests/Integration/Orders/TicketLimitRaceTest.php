<?php

use App\Enums\OrderStatus;
use App\Exceptions\Domain\TicketLimitExceeded;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Orders\ReserveTickets;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 50]);
    $this->buyer = User::factory()->create();
    $this->other = secondConnection();
    $this->items = [['ticketTypeId' => $this->pool->id, 'quantity' => 3]];
});

it('sees an order committed by another request of the same user while waiting for the lock', function () {
    Interleave::before('for update', function () {
        $now = now();

        $orderId = $this->other->table('orders')->insertGetId([
            'user_id' => $this->buyer->id,
            'event_id' => $this->pool->event_id,
            'status' => OrderStatus::Pending->value,
            'expires_at' => $now->copy()->addMinutes(15),
            'total' => 40000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->other->table('order_items')->insert([
            'order_id' => $orderId,
            'ticket_type_id' => $this->pool->id,
            'quantity' => 4,
            'unit_price' => 10000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    });

    expect(fn () => app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, $this->items))
        ->toThrow(TicketLimitExceeded::class);

    expect($this->pool->fresh()->held)->toBe(0)
        ->and(Order::count())->toBe(1);
});

it('keeps the user row locked from counting until the commit', function () {
    $locked = false;

    Interleave::before('update `ticket_types`', function () use (&$locked) {
        $locked = Interleave::rowIsLocked($this->other, 'users', $this->buyer->id);
    });

    app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, $this->items);

    expect($locked)->toBeTrue();
});

it('lets two different users reserve at the same time', function () {
    $otherUser = User::factory()->create();
    $locked = true;

    Interleave::before('update `ticket_types`', function () use ($otherUser, &$locked) {
        $locked = Interleave::rowIsLocked($this->other, 'users', $otherUser->id);
    });

    app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, $this->items);

    expect($locked)->toBeFalse();
});
