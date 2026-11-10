<?php

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentResultHandler;
use App\Data\PaymentStart;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\QueueName;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;
use App\Events\Payments\PaymentFailed;
use App\Exceptions\Domain\PaymentDeclined;
use App\Listeners\Payments\ProcessPayment;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Orders\OrderFixtures;

beforeEach(function () {
    $this->pool = TicketType::factory()->create(['quantity' => 10]);
    $this->order = OrderFixtures::holding($this->pool, 3);
    $this->payment = Payment::factory()->for($this->order)->create();
});

function gatewayAnswering(float $roll): void
{
    app()->instance(PaymentGateway::class, new FakePaymentGateway(0.5, fn () => $roll));
}

it('listens to OrderPlaced on the payments queue', function () {
    Queue::fake();

    event(new OrderPlaced($this->order->id));

    Queue::assertPushedOn(
        QueueName::Payments->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === ProcessPayment::class,
    );
});

it('pays the order when the gateway accepts', function () {
    Event::fake([OrderPaid::class]);
    gatewayAnswering(0.9);

    app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id));

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->payment->fresh()->provider_ref)->toStartWith('fake_');

    Event::assertDispatched(OrderPaid::class);
});

it('fails the order when the gateway declines', function () {
    Event::fake([PaymentFailed::class]);
    gatewayAnswering(0.1);

    app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id));

    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $event) => $event->reason === 'The payment was declined');
});

it('goes through the whole flow from the pay endpoint to sold seats', function () {
    gatewayAnswering(0.9);

    $user = User::find($this->order->user_id);

    $this->payment->delete();
    $this->actingAs($user)->postJson("/api/orders/{$this->order->id}/pay")->assertStatus(202);

    $this->getJson("/api/orders/{$this->order->id}")
        ->assertJsonPath('status', 'PAID')
        ->assertJsonPath('paymentStatus', 'SUCCEEDED');

    expect($this->pool->fresh())->held->toBe(0)->sold->toBe(3);
});

it('gives the seats back after a declined payment in the whole flow', function () {
    gatewayAnswering(0.1);

    $user = User::find($this->order->user_id);

    $this->payment->delete();
    $this->actingAs($user)->postJson("/api/orders/{$this->order->id}/pay")->assertStatus(202);

    expect($this->order->fresh())
        ->status->toBe(OrderStatus::Failed)
        ->seats_released_at->not->toBeNull()
        ->and($this->pool->fresh())->held->toBe(0)->sold->toBe(0);
});

it('does not charge twice when the job runs again', function () {
    $gateway = $this->mock(PaymentGateway::class);

    $gateway->shouldReceive('startPayment')->once()->andReturn(new PaymentStart(providerRef: 'ref_1'));

    $listener = app(ProcessPayment::class);

    $listener->handle(new OrderPlaced($this->order->id));
    $listener->handle(new OrderPlaced($this->order->id));

    expect($this->pool->fresh())->held->toBe(0)->sold->toBe(3);
});

it('leaves at once when the payment is settled', function (PaymentStatus $status) {
    $this->payment->update(['status' => $status]);
    $this->mock(PaymentGateway::class)->shouldNotReceive('startPayment');

    app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id));
})->with([PaymentStatus::Succeeded, PaymentStatus::Failed]);

it('leaves at once when the order has no payment', function () {
    $this->payment->delete();
    $this->mock(PaymentGateway::class)->shouldNotReceive('startPayment');

    app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id));
});

it('waits for the webhook of a redirect gateway', function () {
    $this->mock(PaymentGateway::class)
        ->shouldReceive('startPayment')
        ->andReturn(new PaymentStart(providerRef: 'cs_1', redirectUrl: 'https://pay.example.com/cs_1'));

    $this->mock(PaymentResultHandler::class)->shouldNotReceive('succeeded');

    app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id));

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('leaves the payment pending for a retry when the gateway breaks', function () {
    $this->mock(PaymentGateway::class)->shouldReceive('startPayment')->andThrow(new RuntimeException('Gateway is down'));

    expect(fn () => app(ProcessPayment::class)->handle(new OrderPlaced($this->order->id)))
        ->toThrow(RuntimeException::class, 'Gateway is down');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('does not run two jobs of one order at once', function () {
    $middleware = app(ProcessPayment::class)->middleware(new OrderPlaced(7));

    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('order:7');
});

it('logs the order id when all attempts are used up', function () {
    Log::spy();

    app(ProcessPayment::class)->failed(new OrderPlaced(7), new PaymentDeclined);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['order_id'] === 7);
});
