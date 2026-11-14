<?php

use App\Enums\Role;
use App\Http\Controllers\Waitlist\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:'.Role::Attendee->value])
    ->whereNumber('id')
    ->group(function () {
        Route::get('waitlist', [WaitlistController::class, 'index']);
        Route::post('ticket-types/{id}/waitlist', [WaitlistController::class, 'store']);
        Route::delete('ticket-types/{id}/waitlist', [WaitlistController::class, 'destroy']);
    });
