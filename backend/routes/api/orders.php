<?php

use App\Http\Controllers\Orders\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:ATTENDEE'])
    ->prefix('orders')
    ->whereNumber('id')
    ->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::post('{id}/cancel', [OrderController::class, 'cancel']);
    });
