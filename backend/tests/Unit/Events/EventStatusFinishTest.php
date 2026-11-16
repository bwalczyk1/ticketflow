<?php

use App\Enums\EventStatus;
use App\Services\Events\FinishEndedEvents;

it('derives the finishable statuses from the transition map', function () {
    expect(FinishEndedEvents::finishableStatuses())->toBe([EventStatus::Published]);
});
