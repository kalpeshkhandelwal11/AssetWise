<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryFieldController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\FieldOverrideController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TagBatchController;
use App\Http\Controllers\Admin\TagPrintController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\WorkflowStepController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AttachmentController;
use App\Http\Controllers\Assets\ExportController;
use App\Http\Controllers\Assets\ImportController;
use App\Http\Controllers\Assets\PhotoController;
use App\Http\Controllers\Assets\TagController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Disposal\DisposalController;
use App\Http\Controllers\Movement\MovementBatchController;
use App\Http\Controllers\Movement\MovementController;
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

    // Assets — bulk import/export (M06) register before the resource route so
    // "assets/import" and "assets/export" aren't swallowed by the "assets/{asset}" show route.
    Route::get('assets/import', [ImportController::class, 'index'])->name('assets.import.index');
    Route::get('assets/import/template/{category}', [ImportController::class, 'template'])->name('assets.import.template');
    Route::post('assets/import', [ImportController::class, 'store'])->name('assets.import.store');
    Route::get('assets/import/{batch}', [ImportController::class, 'show'])->name('assets.import.show');
    Route::get('assets/export', [ExportController::class, 'index'])->name('assets.export.index');
    Route::post('assets/export', [ExportController::class, 'store'])->name('assets.export.store');

    Route::resource('assets', AssetController::class);
    Route::post('assets/{asset}/photos', [PhotoController::class, 'store'])->name('assets.photos.store');
    Route::delete('assets/{asset}/photos/{photo}', [PhotoController::class, 'destroy'])->name('assets.photos.destroy');
    Route::patch('assets/{asset}/photos/{photo}/primary', [PhotoController::class, 'setPrimary'])->name('assets.photos.primary');
    Route::post('assets/{asset}/attachments', [AttachmentController::class, 'store'])->name('assets.attachments.store');
    Route::delete('assets/{asset}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('assets.attachments.destroy');

    // Tags (M05)
    Route::post('assets/{asset}/tags/assign', [TagController::class, 'store'])->name('assets.tags.assign');
    Route::get('assets/{asset}/tags/replace', [TagController::class, 'replaceForm'])->name('assets.tags.replace');
    Route::post('assets/{asset}/tags/replace', [TagController::class, 'submitReplacement'])->name('assets.tags.replace.submit');

    // Scan resolver (M05) — contract for M10 audit QR verification
    Route::get('/scan/{tag_number}', [ScanController::class, 'resolve'])->name('scan.resolve');

    // Movements (M09) — bulk routes registered before "movements/create" resource-style
    // route so "movements/bulk/create" isn't swallowed by anything wildcard-shaped.
    Route::prefix('movements')->name('movements.')->group(function () {
        Route::get('/', [MovementController::class, 'index'])->name('index');
        Route::get('bulk/create', [MovementBatchController::class, 'create'])->name('bulk.create');
        Route::post('bulk', [MovementBatchController::class, 'store'])->name('bulk.store');
        Route::get('create', [MovementController::class, 'create'])->name('create');
        Route::post('/', [MovementController::class, 'store'])->name('store');
        Route::post('{movement}/verify', [MovementController::class, 'verify'])->name('verify');
    });

    // Disposal & Scrap (M13)
    Route::prefix('disposals')->name('disposals.')->group(function () {
        Route::get('/', [DisposalController::class, 'index'])->name('index');
        Route::get('create', [DisposalController::class, 'create'])->name('create');
        Route::post('/', [DisposalController::class, 'store'])->name('store');
        Route::get('{disposal}', [DisposalController::class, 'show'])->name('show');
        Route::post('{disposal}/write-off', [DisposalController::class, 'writeOff'])->name('write-off');
        Route::post('{disposal}/scrap', [DisposalController::class, 'scrap'])->name('scrap');
    });

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

        // Users & Roles (M01)
        Route::resource('users', UserController::class)->except(['show']);
        Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::resource('roles', RoleController::class)->except(['show']);

        // Activity log (M01)
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

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

        // Tag pool (M05)
        Route::prefix('tags')->name('tags.')->group(function () {
            Route::get('/', [TagBatchController::class, 'index'])->name('index');
            Route::get('/batches/create', [TagBatchController::class, 'create'])->name('batches.create');
            Route::post('/batches', [TagBatchController::class, 'store'])->name('batches.store');
            Route::get('/print/pdf', [TagPrintController::class, 'pdf'])->name('print.pdf');
            Route::get('/print/word', [TagPrintController::class, 'word'])->name('print.word');
        });
        Route::get('/settings/tags', [SettingController::class, 'edit'])->name('settings.tags.edit');
        Route::patch('/settings/tags', [SettingController::class, 'update'])->name('settings.tags.update');

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
