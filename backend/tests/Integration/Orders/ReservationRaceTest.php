<?php

use App\Exceptions\Domain\ReservationConflict;
use App\Exceptions\Domain\TicketsSoldOut;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Orders\ReserveTickets;
use Illuminate\Database\QueryException;
use Tests\Integration\Orders\Interleave;

const POOL_UPDATE = 'update `ticket_types`';

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->buyer = User::factory()->create();
    $this->other = secondConnection();
});

/**
 * @return list<array{ticketTypeId: int, quantity: int}>
 */
function itemsOf(TicketType $pool, int $quantity): array
{
    return [['ticketTypeId' => $pool->id, 'quantity' => $quantity]];
}

it('retries in a new transaction when another client wins the version', function () {
    Interleave::before(POOL_UPDATE, function () {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['held' => 2, 'version' => 1]);
    });

    app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 3));

    expect($this->pool->fresh())->held->toBe(5)->version->toBe(2)
        ->and(Order::count())->toBe(1);
});

it('answers RESERVATION_CONFLICT when every attempt loses and seats remain', function () {
    $version = 0;

    Interleave::before(POOL_UPDATE, function () use (&$version) {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['version' => ++$version]);
    }, times: PHP_INT_MAX);

    expect(fn () => app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 1)))
        ->toThrow(ReservationConflict::class);

    expect($version)->toBe(config('ticketflow.reservation_attempts'))
        ->and($this->pool->fresh()->held)->toBe(0)
        ->and(Order::count())->toBe(0);
});

it('answers TICKETS_SOLD_OUT when the last lost attempt took the remaining seats', function () {
    $version = 0;

    Interleave::before(POOL_UPDATE, function () use (&$version) {
        $version++;

        $this->other->table('ticket_types')->where('id', $this->pool->id)->update([
            'held' => $version === config('ticketflow.reservation_attempts') ? 10 : 0,
            'version' => $version,
        ]);
    }, times: PHP_INT_MAX);

    expect(fn () => app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 1)))
        ->toThrow(TicketsSoldOut::class);

    expect(Order::count())->toBe(0);
});

it('answers TICKETS_SOLD_OUT on the first retry that finds an empty pool', function () {
    Interleave::before(POOL_UPDATE, function () {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['sold' => 10, 'version' => 1]);
    });

    expect(fn () => app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 1)))
        ->toThrow(TicketsSoldOut::class);

    expect($this->pool->fresh())->sold->toBe(10)->held->toBe(0);
});

it('retries a deadlock in a new transaction', function () {
    Interleave::before(POOL_UPDATE, function () {
        $driverError = new PDOException('Deadlock found when trying to get lock');
        $driverError->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

        throw new QueryException('mysql', POOL_UPDATE, [], $driverError);
    });

    app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 1));

    expect($this->pool->fresh()->held)->toBe(1);
});

it('undoes the first pool when the second one loses its version', function () {
    $second = TicketType::factory()->create(['event_id' => $this->pool->event_id, 'quantity' => 10]);
    $updates = 0;

    Interleave::before(POOL_UPDATE, function () use ($second, &$updates) {
        if (++$updates === 2) {
            $this->other->table('ticket_types')->where('id', $second->id)->update(['version' => 7]);
        }
    }, times: 2);

    app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, [
        ...itemsOf($this->pool, 1),
        ...itemsOf($second, 1),
    ]);

    expect($this->pool->fresh())->held->toBe(1)->version->toBe(1)
        ->and($second->fresh())->held->toBe(1)->version->toBe(8);
});

it('never lets a stale reader oversell the last seats', function () {
    Interleave::before(POOL_UPDATE, function () {
        $this->other->table('ticket_types')->where('id', $this->pool->id)->update(['held' => 9, 'version' => 1]);
    });

    expect(fn () => app(ReserveTickets::class)->handle($this->buyer, $this->pool->event_id, itemsOf($this->pool, 2)))
        ->toThrow(TicketsSoldOut::class);

    expect($this->pool->fresh()->held)->toBe(9);
});
