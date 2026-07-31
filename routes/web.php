<?php

use App\Http\Controllers\Admin\LoginHistoryController;
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
    });
});

require __DIR__.'/auth.php';
