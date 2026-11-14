<?php

use App\Enums\QueueName;
use App\Events\Catalog\EventCancelled;
use App\Events\Refunds\OrderRefunded;
use App\Listeners\Documents\IssueCorrectionInvoice;
use App\Listeners\Orders\ReleaseRefundedSeats;
use App\Listeners\Payments\RefundPayment;
use App\Listeners\Refunds\RefundAllOrders;
use App\Listeners\Refunds\SendRefundEmail;
use App\Listeners\Tickets\VoidTickets;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Consumers of refunds and cancellations and their queues.
 *
 * @return array<string, array{0: object, 1: class-string, 2: QueueName}>
 */
dataset('refund consumers', [
    'RefundPayment' => [fn () => new OrderRefunded(1, 1), RefundPayment::class, QueueName::Payments],
    'VoidTickets' => [fn () => new OrderRefunded(1, 1), VoidTickets::class, QueueName::Tickets],
    'IssueCorrectionInvoice' => [fn () => new OrderRefunded(1, 1), IssueCorrectionInvoice::class, QueueName::Documents],
    'ReleaseRefundedSeats' => [fn () => new OrderRefunded(1, 1), ReleaseRefundedSeats::class, QueueName::Orders],
    'SendRefundEmail' => [fn () => new OrderRefunded(1, 1), SendRefundEmail::class, QueueName::Mail],
    'RefundAllOrders' => [fn () => new EventCancelled(1), RefundAllOrders::class, QueueName::Refunds],
]);

it('registers every consumer of the event', function (object $event, string $listener) {
    Event::fake();

    Event::assertListening($event::class, $listener);
})->with('refund consumers');

it('puts every consumer on its own queue', function (object $event, string $listener, QueueName $queue) {
    Queue::fake();

    event($event);

    Queue::assertPushedOn(
        $queue->value,
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === $listener,
    );
})->with('refund consumers');
