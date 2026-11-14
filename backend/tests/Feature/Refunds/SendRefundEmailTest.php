<?php

use App\Enums\OrderStatus;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Refunds\SendRefundEmail;
use App\Mail\RefundMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SentNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->buyer = User::factory()->create(['name' => 'Zażółć Gęślą', 'email' => 'buyer@example.com']);
    $this->event = Event::factory()->create(['title' => 'Koncert Żółć']);
    $this->order = Order::factory()->for($this->buyer)->refunded()->create(['event_id' => $this->event->id, 'total' => 12000]);
    $this->refund = Refund::factory()->for($this->order)->create(['amount' => 12000, 'reason' => 'Nie mogę przyjść']);
    $this->refunded = new OrderRefunded($this->order->id, $this->refund->id);
});

it('sends once when it runs twice', function () {
    app(SendRefundEmail::class)->handle($this->refunded);
    app(SendRefundEmail::class)->handle($this->refunded);

    Mail::assertSentCount(1);

    expect(SentNotification::query()->where('key', "order:{$this->order->id}:refunded")->count())->toBe(1);
});

it('tells the buyer in Polish how much is coming back', function () {
    app(SendRefundEmail::class)->handle($this->refunded);

    Mail::assertSent(RefundMail::class, function (RefundMail $mail) {
        $mail->assertHasSubject('Zwrot za zamówienie: Koncert Żółć');
        $mail->assertSeeInHtml('Zażółć Gęślą');
        $mail->assertSeeInHtml('120,00 zł');
        $mail->assertSeeInHtml('Nie mogę przyjść');

        return $mail->hasTo('buyer@example.com');
    });
});

it('sends nothing for an order that is not refunded', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Paid]);

    app(SendRefundEmail::class)->handle($this->refunded);

    Mail::assertNothingSent();

    expect(SentNotification::query()->count())->toBe(0);
});

it('does nothing for a missing refund', function () {
    app(SendRefundEmail::class)->handle(new OrderRefunded($this->order->id, 999999));

    Mail::assertNothingSent();
});
