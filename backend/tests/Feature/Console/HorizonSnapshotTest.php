<?php

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

it('takes a Horizon metrics snapshot every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'horizon:snapshot'));

    expect($event?->expression)->toBe('*/5 * * * *');
});
