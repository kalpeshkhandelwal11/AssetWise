<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryFieldController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\FieldOverrideController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\WorkflowStepController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AttachmentController;
use App\Http\Controllers\Assets\PhotoController;
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

    // Assets
    Route::resource('assets', AssetController::class);
    Route::post('assets/{asset}/photos', [PhotoController::class, 'store'])->name('assets.photos.store');
    Route::delete('assets/{asset}/photos/{photo}', [PhotoController::class, 'destroy'])->name('assets.photos.destroy');
    Route::patch('assets/{asset}/photos/{photo}/primary', [PhotoController::class, 'setPrimary'])->name('assets.photos.primary');
    Route::post('assets/{asset}/attachments', [AttachmentController::class, 'store'])->name('assets.attachments.store');
    Route::delete('assets/{asset}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('assets.attachments.destroy');

    // Approvals (M08) — the param is {approval_request}, not {request}, so it never
    // shadows the Illuminate\Http\Request that approve/reject also need.
    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('{approval_request}', [ApprovalController::class, 'show'])->name('show');
        Route::post('{approval_request}/approve', [ApprovalController::class, 'approve'])->name('approve');
        Route::post('{approval_request}/reject', [ApprovalController::class, 'reject'])->name('reject');
    });

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');

        // Asset categories
        Route::resource('categories', CategoryController::class)->except(['show']);

        // Category custom fields (M04)
        Route::prefix('categories/{category}/fields')->name('categories.fields.')->group(function () {
            Route::get('/', [CategoryFieldController::class, 'index'])->name('index');
            Route::get('create', [CategoryFieldController::class, 'create'])->name('create');
            Route::post('/', [CategoryFieldController::class, 'store'])->name('store');
            Route::get('{field}/edit', [CategoryFieldController::class, 'edit'])->name('edit');
            Route::put('{field}', [CategoryFieldController::class, 'update'])->name('update');
            Route::delete('{field}', [CategoryFieldController::class, 'destroy'])->name('destroy');
            Route::post('{field}/override', [FieldOverrideController::class, 'store'])->name('override.store');
            Route::delete('{field}/override', [FieldOverrideController::class, 'destroy'])->name('override.destroy');
        });

        // Approval workflows (M08)
        Route::resource('workflows', WorkflowController::class)->except(['show']);
        Route::prefix('workflows/{workflow}/steps')->name('workflows.steps.')->group(function () {
            Route::post('/', [WorkflowStepController::class, 'store'])->name('store');
            Route::put('{step}', [WorkflowStepController::class, 'update'])->name('update');
            Route::delete('{step}', [WorkflowStepController::class, 'destroy'])->name('destroy');
        });

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
