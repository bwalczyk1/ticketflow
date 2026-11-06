<?php

use App\Http\Controllers\Events\CatalogController;
use App\Http\Controllers\Events\OrganizerEventController;
use App\Http\Controllers\Events\TicketTypeController;
use Illuminate\Support\Facades\Route;

Route::get('events', [CatalogController::class, 'index']);
Route::get('events/{id}', [CatalogController::class, 'show'])->whereNumber('id');

Route::middleware(['auth:sanctum', 'role:ORGANIZER'])
    ->prefix('organizer/events')
    ->whereNumber(['id', 'ticketTypeId'])
    ->group(function () {
        Route::get('/', [OrganizerEventController::class, 'index']);
        Route::post('/', [OrganizerEventController::class, 'store']);
        Route::get('{id}', [OrganizerEventController::class, 'show']);
        Route::patch('{id}', [OrganizerEventController::class, 'update']);
        Route::delete('{id}', [OrganizerEventController::class, 'destroy']);
        Route::post('{id}/publish', [OrganizerEventController::class, 'publish']);
        Route::post('{id}/cancel', [OrganizerEventController::class, 'cancel']);

        Route::get('{id}/ticket-types', [TicketTypeController::class, 'index']);
        Route::post('{id}/ticket-types', [TicketTypeController::class, 'store']);
        Route::get('{id}/ticket-types/{ticketTypeId}', [TicketTypeController::class, 'show']);
        Route::patch('{id}/ticket-types/{ticketTypeId}', [TicketTypeController::class, 'update']);
        Route::delete('{id}/ticket-types/{ticketTypeId}', [TicketTypeController::class, 'destroy']);
    });
