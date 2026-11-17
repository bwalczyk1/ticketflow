<?php

use App\Exceptions\Domain\WaitlistNotNeeded;
use App\Services\Waitlist\WaitlistQueue;

it('offers released seats to the queue in the order of joining', function () {
    // Entry id => quantity, in the order the users joined.
    expect(WaitlistQueue::pick([3 => 1, 7 => 2, 9 => 1], 2))->toBe([3, 9])
        ->and(WaitlistQueue::pick([3 => 1, 7 => 1, 9 => 1], 2))->toBe([3, 7]);
});

it('skips an entry that does not fit and goes on with the next one', function () {
    expect(WaitlistQueue::pick([1 => 4, 2 => 2, 3 => 1], 3))->toBe([2, 3]);
});

it('offers nothing when no seat is free or nobody fits', function (array $waiting, int $seats) {
    expect(WaitlistQueue::pick($waiting, $seats))->toBe([]);
})->with([
    'no seats' => [[1 => 1], 0],
    'nobody fits' => [[1 => 3, 2 => 4], 2],
    'empty queue' => [[], 5],
]);

it('uses every seat when the queue fits exactly', function () {
    expect(WaitlistQueue::pick([1 => 2, 2 => 3], 5))->toBe([1, 2]);
});

it('sends a buyer to the normal purchase while enough seats are free', function (int $free, bool $needed) {
    $check = fn () => WaitlistQueue::ensureNeeded(2, $free);

    $needed ? expect($check)->not->toThrow(WaitlistNotNeeded::class) : expect($check)->toThrow(WaitlistNotNeeded::class);
})->with([
    'sold out' => [0, true],
    'fewer free seats than wanted' => [1, true],
    'exactly enough' => [2, false],
    'more than enough' => [5, false],
]);
