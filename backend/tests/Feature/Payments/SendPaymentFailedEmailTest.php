<?php

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\Payments\PaymentFailed;
use App\Listeners\Payments\SendPaymentFailedEmail;
use App\Mail\PaymentFailedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\SentNotification;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();

    $this->buyer = User::factory()->create(['name' => 'Zażółć Gęślą', 'email' => 'buyer@example.com']);
    $this->event = Event::factory()->create(['title' => 'Koncert Żółć']);

    $this->order = Order::factory()
        ->for($this->buyer)
        ->failed()
        ->create(['event_id' => $this->event->id, 'total' => 12000]);
});

it('sends once when it runs twice', function () {
    $listener = app(SendPaymentFailedEmail::class);

    $listener->handle(new PaymentFailed($this->order->id, 'Card declined'));
    $listener->handle(new PaymentFailed($this->order->id, 'Card declined'));

    Mail::assertSentCount(1);

    expect(SentNotification::query()->where('key', "order:{$this->order->id}:payment-failed")->count())->toBe(1);
});

it('tells the buyer that the payment failed', function () {
    app(SendPaymentFailedEmail::class)->handle(new PaymentFailed($this->order->id, 'Card declined'));

    Mail::assertSent(PaymentFailedMail::class, fn (PaymentFailedMail $mail) => $mail->hasTo('buyer@example.com'));
});

it('sends nothing for an order that did not fail', function () {
    Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Paid]);

    app(SendPaymentFailedEmail::class)->handle(new PaymentFailed($this->order->id, 'Card declined'));

    Mail::assertNothingSent();

    expect(SentNotification::query()->count())->toBe(0);
});

it('does nothing for a missing order', function () {
    app(SendPaymentFailedEmail::class)->handle(new PaymentFailed(999999, 'Card declined'));

    Mail::assertNothingSent();
});

it('writes in Polish with the event title and the amount', function () {
    $order = Order::query()
        ->with(['user:id,name,email', 'event:id,title'])
        ->find($this->order->id, ['id', 'user_id', 'event_id', 'total']);

    $mail = new PaymentFailedMail($order);

    $mail->assertHasSubject('Płatność nieudana: Koncert Żółć');
    $mail->assertSeeInHtml('Zażółć Gęślą');
    $mail->assertSeeInHtml('nie zostało opłacone');
    $mail->assertSeeInHtml('wróciły do puli');
    $mail->assertSeeInHtml('nowe zamówienie');
    $mail->assertSeeInHtml('120,00 zł');
});

it('runs on the mail queue', function () {
    Queue::fake();

    event(new PaymentFailed($this->order->id, 'Card declined'));

    Queue::assertPushedOn(
        QueueName::Mail->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === SendPaymentFailedEmail::class,
    );
});

it('logs only the order id and the error class when all attempts are used up', function () {
    Log::spy();

    app(SendPaymentFailedEmail::class)->failed(new PaymentFailed(7, 'Card declined'), new RuntimeException('SMTP is down for buyer@example.com'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context === ['order_id' => 7, 'error' => RuntimeException::class]);
});
