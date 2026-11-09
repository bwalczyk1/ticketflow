<?php

it('runs on the test configuration, not on the container environment', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.database'))->toStartWith('ticketflow_test');
});
