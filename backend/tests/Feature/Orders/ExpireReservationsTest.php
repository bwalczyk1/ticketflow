<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\ReservationExpired;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Orders\ExpireReservations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 3);
});

it('expires a pending order after its hold and dispatches the event', function () {
    Event::fake([ReservationExpired::class]);

    $this->travelTo($this->order->expires_at->addMinute());

    expect(app(ExpireReservations::class)->handle())->toBe(1)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Expired);

    Event::assertDispatched(ReservationExpired::class, fn (ReservationExpired $event) => $event->orderId === $this->order->id);
});

it('gives the seats back through the queued listener', function () {
    $this->travelTo($this->order->expires_at);

    app(ExpireReservations::class)->handle();

    expect($this->pool->fresh()->held)->toBe(0)
        ->and($this->order->fresh()->seats_released_at)->not->toBeNull();
});

it('expires from the exact second of expires_at and not before', function (int $seconds, OrderStatus $status) {
    Event::fake([ReservationExpired::class]);

    $this->travelTo($this->order->expires_at->addSeconds($seconds));

    app(ExpireReservations::class)->handle();

    expect($this->order->fresh()->status)->toBe($status);
})->with([
    'one second before' => [-1, OrderStatus::Pending],
    'on the boundary' => [0, OrderStatus::Expired],
    'one second after' => [1, OrderStatus::Expired],
]);

it('skips an order with a pending payment younger than 5 minutes', function () {
    Event::fake([ReservationExpired::class]);

    Payment::factory()->for($this->order)->create(['created_at' => $this->order->expires_at->subMinutes(2)]);

    $this->travelTo($this->order->expires_at->addMinutes(3)->subSecond());

    expect(app(ExpireReservations::class)->handle())->toBe(0)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Pending);

    Event::assertNotDispatched(ReservationExpired::class);
});

it('expires an order whose pending payment is exactly 5 minutes old', function () {
    Event::fake([ReservationExpired::class]);

    Payment::factory()->for($this->order)->create(['created_at' => $this->order->expires_at->subMinutes(2)]);

    $this->travelTo($this->order->expires_at->addMinutes(3));

    app(ExpireReservations::class)->handle();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired);
});

it('does not count a settled payment as running', function () {
    Event::fake([ReservationExpired::class]);

    $this->travelTo($this->order->expires_at);

    Payment::factory()->for($this->order)->create(['status' => PaymentStatus::Failed]);

    app(ExpireReservations::class)->handle();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired);
});

it('leaves orders past the pending stage alone', function (OrderStatus $status) {
    Event::fake([ReservationExpired::class]);

    Order::query()->whereKey($this->order->id)->update(['status' => $status]);

    $this->travelTo($this->order->expires_at->addHour());

    expect(app(ExpireReservations::class)->handle())->toBe(0)
        ->and($this->order->fresh()->status)->toBe($status);

    Event::assertNotDispatched(ReservationExpired::class);
})->with([OrderStatus::Paid, OrderStatus::Failed, OrderStatus::Cancelled, OrderStatus::Refunded]);

it('dispatches the event once over two runs', function () {
    Event::fake([ReservationExpired::class]);

    $this->travelTo($this->order->expires_at);

    $expire = app(ExpireReservations::class);

    expect($expire->handle())->toBe(1)
        ->and($expire->handle())->toBe(0);

    Event::assertDispatchedTimes(ReservationExpired::class, 1);
});

it('is available as a command', function () {
    Event::fake([ReservationExpired::class]);

    $this->travelTo($this->order->expires_at);

    $this->artisan('ticketflow:expire-reservations')
        ->expectsOutput('Expired reservations: 1')
        ->assertSuccessful();
});

it('runs every minute', function () {
    app(Kernel::class)->bootstrap();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'ticketflow:expire-reservations'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
