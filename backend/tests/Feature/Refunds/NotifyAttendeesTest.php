<?php

use App\Enums\OrderStatus;
use App\Events\Catalog\EventCancelled;
use App\Listeners\Refunds\NotifyAttendees;
use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SentNotification;
use App\Models\User;
use App\Services\Refunds\StartRefund;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->event = Event::factory()->cancelled()->create(['title' => 'Koncert Żółć']);
    $this->buyer = User::factory()->create(['name' => 'Zażółć Gęślą', 'email' => 'buyer@example.com']);
    $this->cancelled = new EventCancelled($this->event->id);
});

function orderFor(User $user, Event $event, OrderStatus $status, int $total = 10000): Order
{
    return Order::factory()->for($user)->create(['event_id' => $event->id, 'status' => $status, 'total' => $total]);
}

it('sends one Polish e-mail per attendee with the amount of all paid orders', function () {
    orderFor($this->buyer, $this->event, OrderStatus::Paid, 12000);
    orderFor($this->buyer, $this->event, OrderStatus::Paid, 3000);

    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertSentCount(1);
    Mail::assertSent(EventCancelledMail::class, function (EventCancelledMail $mail) {
        $mail->assertHasSubject('Wydarzenie odwołane: Koncert Żółć');
        $mail->assertSeeInHtml('Zażółć Gęślą');
        $mail->assertSeeInHtml('150,00 zł');

        return $mail->hasTo('buyer@example.com');
    });
});

it('also mails attendees whose orders the cancellation already refunded', function () {
    $order = orderFor($this->buyer, $this->event, OrderStatus::Refunded, 12000);

    Refund::factory()->for($order)->create(['amount' => 12000, 'reason' => StartRefund::EVENT_CANCELLED_REASON]);

    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertSent(EventCancelledMail::class, fn (EventCancelledMail $mail) => $mail->hasTo('buyer@example.com'));
});

it('skips attendees without a paid order', function (OrderStatus $status) {
    orderFor($this->buyer, $this->event, $status);

    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertNothingSent();
})->with([OrderStatus::Pending, OrderStatus::Cancelled, OrderStatus::Expired, OrderStatus::Failed]);

it('skips an attendee who refunded the order before the cancellation', function () {
    $order = orderFor($this->buyer, $this->event, OrderStatus::Refunded);

    Refund::factory()->for($order)->create(['reason' => 'Nie mogę przyjść']);

    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertNothingSent();
});

it('sends once per attendee when it runs twice', function () {
    $other = User::factory()->create();

    orderFor($this->buyer, $this->event, OrderStatus::Paid);
    orderFor($other, $this->event, OrderStatus::Paid);

    app(NotifyAttendees::class)->handle($this->cancelled);
    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertSentCount(2);

    expect(SentNotification::query()->where('key', "event:{$this->event->id}:cancelled:user:{$this->buyer->id}")->count())->toBe(1);
});

it('sends nothing for an event that is not cancelled', function () {
    Event::query()->whereKey($this->event->id)->update(['status' => 'PUBLISHED']);
    orderFor($this->buyer, $this->event, OrderStatus::Paid);

    app(NotifyAttendees::class)->handle($this->cancelled);

    Mail::assertNothingSent();
});
