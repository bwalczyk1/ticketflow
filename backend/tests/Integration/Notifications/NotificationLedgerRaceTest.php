<?php

use App\Services\Notifications\NotificationLedger;

it('does not send a key already committed by another connection', function () {
    secondConnection()->table('sent_notifications')->insert(['key' => 'order:1:tickets', 'sent_at' => now()]);
    $sent = false;

    $result = (new NotificationLedger)->once('order:1:tickets', function () use (&$sent) {
        $sent = true;
    });

    expect($result)->toBeFalse()
        ->and($sent)->toBeFalse();
});
