<?php

use App\Models\SentNotification;
use App\Services\Notifications\NotificationLedger;

it('sends once and records the key', function () {
    $sent = 0;

    $result = (new NotificationLedger)->once('order:1:tickets', function () use (&$sent) {
        $sent++;
    });

    expect($result)->toBeTrue()
        ->and($sent)->toBe(1)
        ->and(SentNotification::query()->where('key', 'order:1:tickets')->exists())->toBeTrue();
});

it('does not send the same key twice', function () {
    $ledger = new NotificationLedger;
    $sent = 0;

    $send = function () use (&$sent) {
        $sent++;
    };

    expect($ledger->once('order:1:tickets', $send))->toBeTrue()
        ->and($ledger->once('order:1:tickets', $send))->toBeFalse()
        ->and($sent)->toBe(1);
});

it('treats different keys independently', function () {
    $ledger = new NotificationLedger;
    $send = fn () => null;

    expect($ledger->once('order:1:tickets', $send))->toBeTrue()
        ->and($ledger->once('order:2:tickets', $send))->toBeTrue();
});

it('rolls the claim back when sending fails, so a retry sends', function () {
    $ledger = new NotificationLedger;

    expect(fn () => $ledger->once('order:1:tickets', function () {
        throw new RuntimeException('smtp down');
    }))->toThrow(RuntimeException::class, 'smtp down');

    expect(SentNotification::query()->where('key', 'order:1:tickets')->exists())->toBeFalse();

    $sent = false;

    $result = $ledger->once('order:1:tickets', function () use (&$sent) {
        $sent = true;
    });

    expect($result)->toBeTrue()
        ->and($sent)->toBeTrue();
});
