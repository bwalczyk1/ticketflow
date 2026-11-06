<?php

use App\Http\Controllers\Events\CatalogController;
use App\Http\Controllers\Events\OrganizerEventController;
use App\Http\Controllers\Events\TicketTypeController;
use Illuminate\Support\Facades\Route;

// Longer ids cannot exist and would overflow an int parameter, so they end as a plain 404.
$id = '[0-9]{1,18}';

Route::get('events', [CatalogController::class, 'index']);
Route::get('events/{event}', [CatalogController::class, 'show'])->where('event', $id);

Route::middleware(['auth:sanctum', 'role:ORGANIZER'])
    ->prefix('organizer/events')
    ->where(['event' => $id, 'ticketType' => $id])
    ->group(function () {
        Route::get('/', [OrganizerEventController::class, 'index']);
        Route::post('/', [OrganizerEventController::class, 'store']);
        Route::get('{event}', [OrganizerEventController::class, 'show']);
        Route::patch('{event}', [OrganizerEventController::class, 'update']);
        Route::delete('{event}', [OrganizerEventController::class, 'destroy']);
        Route::post('{event}/publish', [OrganizerEventController::class, 'publish']);
        Route::post('{event}/cancel', [OrganizerEventController::class, 'cancel']);

        Route::get('{event}/ticket-types', [TicketTypeController::class, 'index']);
        Route::post('{event}/ticket-types', [TicketTypeController::class, 'store']);
        Route::get('{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'show']);
        Route::patch('{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
        Route::delete('{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);
    });
