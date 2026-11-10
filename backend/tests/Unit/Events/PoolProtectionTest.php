<?php

use App\Exceptions\Domain\PoolProtected;
use App\Services\Events\PoolProtection;

it('refuses a quantity below sold plus held', function (int $quantity, int $sold, int $held) {
    PoolProtection::ensureQuantity($quantity, $sold, $held);
})->with([
    'one below' => [9, 6, 4],
    'below sold alone' => [5, 6, 0],
    'below held alone' => [3, 0, 4],
])->throws(PoolProtected::class);

it('allows a quantity equal to sold plus held', function () {
    PoolProtection::ensureQuantity(10, 6, 4);
})->throwsNoExceptions();

it('allows a larger quantity', function () {
    PoolProtection::ensureQuantity(11, 6, 4);
})->throwsNoExceptions();
