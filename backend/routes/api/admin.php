<?php

use App\Enums\Role;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'role:'.Role::Admin->value])->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
});
