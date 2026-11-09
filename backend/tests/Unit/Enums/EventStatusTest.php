<?php

use App\Enums\EventStatus;

dataset('event transitions', [
    [EventStatus::Draft, EventStatus::Published, true],
    [EventStatus::Draft, EventStatus::Finished, false],
    [EventStatus::Draft, EventStatus::Cancelled, false],
    [EventStatus::Published, EventStatus::Finished, true],
    [EventStatus::Published, EventStatus::Cancelled, true],
    [EventStatus::Published, EventStatus::Draft, false],
    [EventStatus::Finished, EventStatus::Cancelled, false],
    [EventStatus::Cancelled, EventStatus::Published, false],
]);

it('allows only the mapped transitions', function (EventStatus $from, EventStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with('event transitions');
