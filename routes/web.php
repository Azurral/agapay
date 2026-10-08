<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\BeneficiaryLookupController;
use App\Http\Controllers\BeneficiaryRegistrationController;
use App\Http\Controllers\DamageExportController;
use App\Http\Controllers\DamagePhotoController;
use App\Http\Controllers\DamageReferenceController;
use App\Http\Controllers\DamageReportActionController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionCycleController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\InterventionRecordActionController;
use App\Http\Controllers\InterventionRecordController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ValidationQueueController;
use App\Models\Intervention;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/switch-account', [LoginController::class, 'switch'])->name('account.switch');
});

// Guests see the public landing page; signed-in users land on their dashboard.
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

    Route::get('/search', SearchController::class)->middleware('can:beneficiaries.view')->name('search');

    Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->middleware('can:beneficiaries.manage')->name('beneficiaries.index');
    // Registered before /beneficiaries/{beneficiary} so "create" is not read as a profile id.
    Route::get('/beneficiaries/create', [BeneficiaryRegistrationController::class, 'create'])->middleware('can:rsbsa.register')->name('beneficiaries.create');
    Route::post('/beneficiaries', [BeneficiaryRegistrationController::class, 'store'])->middleware('can:rsbsa.register')->name('beneficiaries.store');
    Route::get('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'show'])->middleware('can:beneficiaries.view')->name('beneficiaries.show');
    Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->middleware('can:beneficiaries.manage')->name('beneficiaries.update');

    Route::middleware('can:interventions.view')->group(function () {
        Route::get('/interventions', [InterventionController::class, 'index'])->name('interventions.index');
        Route::get('/interventions/da', [InterventionController::class, 'list'])->defaults('source', Intervention::SOURCE_DA)->name('interventions.da');
        Route::get('/interventions/lgu', [InterventionController::class, 'list'])->defaults('source', Intervention::SOURCE_LGU)->name('interventions.lgu');
    });
    Route::middleware('can:interventions.archive')->group(function () {
        Route::get('/interventions/{source}/archived', [InterventionController::class, 'archived'])
            ->whereIn('source', Intervention::SOURCES)->name('interventions.archived');
        Route::post('/intervention-records/{record}/archive', [InterventionRecordActionController::class, 'archive'])->name('intervention-records.archive');
        Route::post('/intervention-records/{record}/restore', [InterventionRecordActionController::class, 'restore'])
            ->withTrashed()->name('intervention-records.restore');
    });
    Route::middleware('can:intervention_records.manage')->group(function () {
        Route::get('/intervention-records', [InterventionRecordController::class, 'index'])->name('intervention-records.index');
        Route::get('/intervention-records/create', [InterventionRecordController::class, 'create'])->name('intervention-records.create');
        Route::post('/intervention-records', [InterventionRecordController::class, 'store'])->name('intervention-records.store');
        Route::get('/intervention-records/{record}/edit', [InterventionRecordController::class, 'edit'])->name('intervention-records.edit');
        Route::put('/intervention-records/{record}', [InterventionRecordController::class, 'update'])->name('intervention-records.update');
    });
    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('can:inventory.view')->name('inventory.index');
    Route::post('/inventory/movements', [InventoryController::class, 'storeMovement'])->middleware('can:inventory.manage')->name('inventory.movements.store');
    Route::middleware('can:inventory.manage')->group(function () {
        Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items.store');
        Route::put('/inventory/items/{item}', [InventoryController::class, 'updateItem'])->name('inventory.items.update');
    });
    // Farmer search for the Add Record and New Damage Report forms (existing profiles only).
    Route::get('/beneficiary-lookup', BeneficiaryLookupController::class)
        ->middleware('can.any:intervention_records.manage,damage.create')->name('beneficiaries.lookup');

    Route::middleware('can:damage.create')->group(function () {
        Route::get('/damage-reports/create', [DamageReportController::class, 'create'])->name('damage.create');
        Route::post('/damage-reports', [DamageReportController::class, 'store'])->name('damage.store');
        Route::get('/damage-reports/{report}/edit', [DamageReportController::class, 'edit'])->name('damage.edit');
        Route::put('/damage-reports/{report}', [DamageReportController::class, 'update'])->name('damage.update');
    });
    Route::middleware('can:damage.view')->group(function () {
        Route::get('/damage-reports', [DamageReportController::class, 'index'])->name('damage.index');
        // Before /damage-reports/{report}, so "export" and "pdf" are not read as report ids.
        Route::get('/damage-reports/export', [DamageExportController::class, 'excel'])->middleware('can:export.run')->name('damage.export');
        Route::get('/damage-reports/pdf', [DamageExportController::class, 'pdf'])->name('damage.pdf');
        Route::get('/damage-reports/{report}', [DamageReportController::class, 'show'])->withTrashed()->name('damage.show');
        Route::get('/damage-photos/{photo}', DamagePhotoController::class)->name('damage.photos.show');
    });

    Route::post('/damage-reports/{report}/validate', [DamageReportActionController::class, 'validate'])
        ->middleware('can:damage.validate')->name('damage.validate');
    Route::middleware('can:damage.configure')->group(function () {
        Route::put('/damage-reference/crops', [DamageReferenceController::class, 'updateCrops'])->name('damage.crops.update');
        Route::post('/damage-reference/disasters', [DamageReferenceController::class, 'storeDisaster'])->name('damage.disasters.store');
        Route::post('/damage-reports/{report}/archive', [DamageReportActionController::class, 'archive'])->name('damage.archive');
        Route::post('/damage-reports/{report}/restore', [DamageReportActionController::class, 'restore'])->withTrashed()->name('damage.restore');
    });

    Route::middleware('can:cycles.manage')->group(function () {
        Route::get('/distribution-cycles', [DistributionCycleController::class, 'index'])->name('cycles.index');
        Route::post('/distribution-cycles', [DistributionCycleController::class, 'store'])->name('cycles.store');
        Route::put('/distribution-cycles/{cycle}', [DistributionCycleController::class, 'update'])->name('cycles.update');
    });

    Route::middleware('can:reports.generate')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    });

    Route::middleware('can:export.run')->group(function () {
        Route::get('/export', [ExportController::class, 'index'])->name('export.index');
        Route::get('/export/download', [ExportController::class, 'download'])->name('export.download');
    });
    Route::middleware('can:import.run')->group(function () {
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        Route::post('/import/{batch}/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
        Route::post('/import/{batch}/discard', [ImportController::class, 'discard'])->name('import.discard');
    });
    Route::get('/validation', ValidationQueueController::class)->middleware('can:interventions.validate')->name('validation.index');
    Route::post('/intervention-records/{record}/validation', [InterventionRecordActionController::class, 'validate'])
        ->middleware('can:interventions.validate')->name('intervention-records.validate');
    Route::middleware('can:interventions.claim')->group(function () {
        Route::post('/intervention-records/{record}/claim', [InterventionRecordActionController::class, 'claim'])->name('intervention-records.claim');
        Route::post('/intervention-records/{record}/unclaim', [InterventionRecordActionController::class, 'unclaim'])->name('intervention-records.unclaim');
    });

    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::put('/roles/permissions', [RolePermissionController::class, 'update'])
        ->middleware('can:roles.configure')->name('roles.permissions.update');
});
