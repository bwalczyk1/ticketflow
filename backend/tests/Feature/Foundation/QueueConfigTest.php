<?php

use App\Enums\QueueName;

it('has one supervisor for every queue', function () {
    $supervisors = config('horizon.defaults');

    foreach (QueueName::cases() as $queue) {
        expect($supervisors)->toHaveKey("supervisor-{$queue->value}")
            ->and($supervisors["supervisor-{$queue->value}"]['queue'])->toBe([$queue->value]);
    }
});

it('keeps every job timeout below the retry_after of the connection', function () {
    $retryAfter = config('queue.connections.redis.retry_after');

    foreach (config('horizon.defaults') as $supervisor) {
        expect($supervisor['timeout'])->toBeLessThan($retryAfter);
    }
});
