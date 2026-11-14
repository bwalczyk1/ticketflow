<?php

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Tickets\IssueTickets;
use App\Listeners\Tickets\VoidTickets;
use App\Models\Refund;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Tickets\TicketFixtures;
use Tests\Integration\Orders\Interleave;

beforeEach(function () {
    Bus::fake();

    $this->order = TicketFixtures::paidOrder([2]);
    $this->other = secondConnection();
});

it('issues no valid tickets when the refund committed right before the order lock', function () {
    Interleave::before(Interleave::ORDER_LOCK, function () {
        $this->other->table('orders')->where('id', $this->order->id)->update(['status' => OrderStatus::Refunded->value]);
    });

    app(IssueTickets::class)->handle(new OrderPaid($this->order->id));

    expect(TicketFixtures::ticketsOf($this->order)->where('status', TicketStatus::Valid))->toHaveCount(0);
});

it('keeps the order locked while the tickets are inserted, so a refund waits for them', function () {
    $locked = false;

    Interleave::before('insert ignore into `tickets`', function () use (&$locked) {
        $locked = Interleave::rowIsLocked($this->other, 'orders', $this->order->id);
    });

    app(IssueTickets::class)->handle(new OrderPaid($this->order->id));

    expect($locked)->toBeTrue();
});

it('keeps the order locked while the tickets are voided', function () {
    TicketFixtures::issueTickets($this->order);
    $this->order->update(['status' => OrderStatus::Refunded]);

    $refund = Refund::factory()->for($this->order)->create();
    $locked = false;

    Interleave::before('update `tickets`', function () use (&$locked) {
        $locked = Interleave::rowIsLocked($this->other, 'orders', $this->order->id);
    });

    app(VoidTickets::class)->handle(new OrderRefunded($this->order->id, $refund->id));

    expect($locked)->toBeTrue()
        ->and(TicketFixtures::ticketsOf($this->order)->pluck('status')->unique()->all())->toBe([TicketStatus::Void]);
});

it('gives no valid tickets when the refund overtakes ticket issuing', function () {
    $this->order->update(['status' => OrderStatus::Refunded]);

    $refund = Refund::factory()->for($this->order)->create();

    app(VoidTickets::class)->handle(new OrderRefunded($this->order->id, $refund->id));
    app(IssueTickets::class)->handle(new OrderPaid($this->order->id));

    expect(TicketFixtures::ticketsOf($this->order))->toHaveCount(0);
});
