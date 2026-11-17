<?php

use App\Enums\QueueName;
use App\Jobs\Documents\GenerateTicketPdfs;
use App\Jobs\Notifications\SendEventReminder;
use App\Jobs\Payments\ProcessRefund;
use App\Jobs\Refunds\RefundOrder;
use Illuminate\Support\Facades\Queue;

/**
 * @return array<string, array{0: Closure(): object, 1: QueueName}>
 */
dataset('jobs', fn () => [
    'GenerateTicketPdfs' => [fn () => new GenerateTicketPdfs(1), QueueName::Documents],
    'SendEventReminder' => [fn () => new SendEventReminder(1, 1), QueueName::Mail],
    'ProcessRefund' => [fn () => new ProcessRefund(1), QueueName::Payments],
    'RefundOrder' => [fn () => new RefundOrder(1), QueueName::Refunds],
]);

it('dispatches every job on its own queue', function (Closure $job, QueueName $queue) {
    Queue::fake();

    $instance = $job();

    dispatch($instance);

    Queue::assertPushedOn($queue->value, $instance::class);
})->with('jobs');
