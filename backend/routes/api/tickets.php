<?php

use App\Enums\Role;
use App\Http\Controllers\Tickets\CheckInController;
use App\Http\Controllers\Tickets\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:'.Role::Attendee->value])
    ->prefix('tickets')
    ->whereNumber('id')
    ->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('{id}/pdf', [TicketController::class, 'pdf']);
    });

Route::middleware(['auth:sanctum', 'role:'.Role::Organizer->value])
    ->post('organizer/events/{id}/check-in', [CheckInController::class, 'store'])
    ->whereNumber('id');
