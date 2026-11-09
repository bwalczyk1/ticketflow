<?php

use App\Http\Controllers\Foundation\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);
