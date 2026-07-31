<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified', 'force.password.change'])
    ->name('dashboard');

// Force password change (auth required, but exempt from itself)
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [ForcePasswordChangeController::class, 'show'])
        ->name('password.force-change');
    Route::put('/password/change', [ForcePasswordChangeController::class, 'update'])
        ->name('password.force-change.update');
});

// Authenticated routes (all require login + force-change check)
Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');

        // Companies
        Route::resource('companies', CompanyController::class)->except(['show']);
        Route::patch('companies/{company}/toggle', [CompanyController::class, 'toggleActive'])->name('companies.toggle');

        // Generic shared masters (statuses, asset-types, priorities, movement-types, etc.)
        Route::get('masters', [MasterController::class, 'landing'])->name('masters.landing');
        Route::get('masters/{entity}', [MasterController::class, 'index'])->name('masters.index');
        Route::post('masters/{entity}', [MasterController::class, 'store'])->name('masters.store');
        Route::put('masters/{entity}/{id}', [MasterController::class, 'update'])->name('masters.update');
        Route::patch('masters/{entity}/{id}/toggle', [MasterController::class, 'toggleActive'])->name('masters.toggle');
        Route::delete('masters/{entity}/{id}', [MasterController::class, 'destroy'])->name('masters.destroy');

        // Location hierarchy
        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/', [LocationController::class, 'index'])->name('index');
            Route::post('/', [LocationController::class, 'store'])->name('store');
            Route::put('{location}', [LocationController::class, 'update'])->name('update');
            Route::delete('{location}', [LocationController::class, 'destroy'])->name('destroy');
            Route::post('{location}/buildings', [LocationController::class, 'storeBuilding'])->name('buildings.store');
        });

        Route::prefix('buildings')->name('locations.buildings.')->group(function () {
            Route::put('{building}', [LocationController::class, 'updateBuilding'])->name('update');
            Route::delete('{building}', [LocationController::class, 'destroyBuilding'])->name('destroy');
            Route::post('{building}/floors', [LocationController::class, 'storeFloor'])->name('floors.store');
        });

        Route::prefix('floors')->name('locations.floors.')->group(function () {
            Route::put('{floor}', [LocationController::class, 'updateFloor'])->name('update');
            Route::delete('{floor}', [LocationController::class, 'destroyFloor'])->name('destroy');
            Route::post('{floor}/rooms', [LocationController::class, 'storeRoom'])->name('rooms.store');
        });

        Route::prefix('rooms')->name('locations.rooms.')->group(function () {
            Route::put('{room}', [LocationController::class, 'updateRoom'])->name('update');
            Route::delete('{room}', [LocationController::class, 'destroyRoom'])->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';
