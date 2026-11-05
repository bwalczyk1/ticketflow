<?php

use App\Http\Controllers\Events\CatalogController;
use Illuminate\Support\Facades\Route;

// Longer ids cannot exist and would overflow an int parameter, so they end as a plain 404.
$id = '[0-9]{1,18}';

Route::get('events', [CatalogController::class, 'index']);
Route::get('events/{event}', [CatalogController::class, 'show'])->where('event', $id);
