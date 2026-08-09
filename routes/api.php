<?php

use App\Http\Controllers\Api\DynamicFieldController;
use App\Http\Controllers\Api\LocationCascadeController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

// Session-authenticated JSON endpoints consumed by Alpine.js on the asset form
// (and reused by M09 movement forms). Explicitly runs the 'web' middleware group
// so the auth session cookie is read, since routes/api.php defaults to the
// stateless 'api' group.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/buildings', [LocationCascadeController::class, 'buildings']);
    Route::get('/floors', [LocationCascadeController::class, 'floors']);
    Route::get('/rooms', [LocationCascadeController::class, 'rooms']);
    Route::get('/categories/{category}/fields', [DynamicFieldController::class, 'forCategory']);

    // Mobile scan logging (M05) — html5-qrcode client posts here after a camera read.
    Route::post('/scan', [ScanController::class, 'log'])->name('api.scan.log');
});
