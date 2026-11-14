<?php

use App\Services\Orders\ExpireReservations;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ticketflow:expire-reservations', function (ExpireReservations $expireReservations) {
    /** @var Command $this */
    $this->line('Expired reservations: '.$expireReservations->handle());
})->purpose('Expire pending orders whose seat hold has ended');

Schedule::command('ticketflow:expire-reservations')->everyMinute();
Schedule::command('ticketflow:finish-events')->everyFiveMinutes();
Schedule::command('ticketflow:reconcile-orders')->everyFiveMinutes();

// Waitlist, check-in and event notifications.
Schedule::command('ticketflow:expire-waitlist-offers')->everyMinute();
