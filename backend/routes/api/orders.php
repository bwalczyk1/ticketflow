<?php

use App\Http\Controllers\Orders\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:ATTENDEE'])
    ->prefix('orders')
    ->whereNumber('id')
    ->group(function () {
        Route::post('/', [OrderController::class, 'store']);
    });
