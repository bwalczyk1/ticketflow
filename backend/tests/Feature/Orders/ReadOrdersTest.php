<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->attendee = User::factory()->create();
    $this->actingAs($this->attendee);
});

function orderOf(User $user, array $attributes = []): Order
{
    $order = Order::factory()->for($user)->create($attributes);

    OrderItem::factory()->for($order)->create();

    return $order;
}

it('lists only own orders, newest first, in the list format', function () {
    $older = orderOf($this->attendee, ['created_at' => now()->subHour()]);
    $newer = orderOf($this->attendee);

    orderOf(User::factory()->create());

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 2, 'totalPages' => 1]);
});

it('paginates and sorts the list by a whitelisted field', function () {
    $cheap = orderOf($this->attendee, ['total' => 100]);

    orderOf($this->attendee, ['total' => 200]);

    $this->getJson('/api/orders?sort=total&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $cheap->id)
        ->assertJsonPath('meta.totalPages', 2);
});

it('filters own orders by status', function () {
    $paid = orderOf($this->attendee, ['status' => 'PAID']);

    orderOf($this->attendee);
    orderOf(User::factory()->create(), ['status' => 'PAID']);

    $this->getJson('/api/orders?status=PAID')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $paid->id)
        ->assertJsonPath('meta.total', 1);
});

it('rejects an unknown status filter with 400', function (string $status) {
    $this->getJson("/api/orders?status={$status}")
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'status');
})->with(['SHIPPED', 'paid']);

it('rejects a sort outside the whitelist', function () {
    $this->getJson('/api/orders?sort=user_id')->assertStatus(400);
});

it('lists orders without lazy loading', function () {
    orderOf($this->attendee);
    orderOf($this->attendee);

    $this->getJson('/api/orders')->assertOk()->assertJsonCount(2, 'data');
});

it('shows an own order with its payment status', function () {
    $order = orderOf($this->attendee);

    Payment::factory()->for($order)->create();

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('id', $order->id)
        ->assertJsonPath('paymentStatus', 'PENDING')
        ->assertJsonCount(1, 'items')
        ->assertJsonStructure(['id', 'status', 'total', 'expiresAt', 'createdAt', 'paymentStatus', 'event' => ['id', 'title', 'venue', 'city', 'timezone', 'startsAt'], 'items' => [['ticketTypeId', 'name', 'quantity', 'unitPrice']]]);
});

it('answers 404 for an order of another user', function () {
    $foreign = orderOf(User::factory()->create());

    $this->getJson("/api/orders/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('answers 404 for a missing order', function () {
    $this->getJson('/api/orders/999999')->assertNotFound();
});

it('is closed to guests and other roles', function () {
    $order = orderOf($this->attendee);

    auth()->forgetGuards();

    $this->getJson('/api/orders')->assertUnauthorized();
    $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());

    $this->getJson('/api/orders')->assertForbidden();
    $this->getJson("/api/orders/{$order->id}")->assertForbidden();
});

it('answers 404 for a non-numeric id', function () {
    $this->getJson('/api/orders/abc')->assertNotFound();
});
