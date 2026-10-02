<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\RsbsaRegistrationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Services\RsbsaWorkflow;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/switch-account', [LoginController::class, 'switch'])->name('account.switch');
});

// Guests go / -> /dashboard -> /login; signed-in users land on their dashboard.
Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->middleware('can:audit.view')->name('audit.index');

    Route::get('/search', SearchController::class)->middleware('can:beneficiaries.view')->name('search');

    Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->middleware('can:beneficiaries.manage')->name('beneficiaries.index');
    Route::get('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'show'])->middleware('can:beneficiaries.view')->name('beneficiaries.show');
    Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->middleware('can:beneficiaries.manage')->name('beneficiaries.update');

    Route::get('/rsbsa/register', [RsbsaRegistrationController::class, 'create'])
        ->middleware('can.any:rsbsa.register,rsbsa.process')->name('rsbsa.register');
    Route::post('/rsbsa/register', [RsbsaRegistrationController::class, 'store'])
        ->middleware('can:rsbsa.register')->name('rsbsa.store');
    Route::post('/rsbsa/{beneficiary}/{action}', [RsbsaRegistrationController::class, 'transition'])
        ->middleware('can:rsbsa.process')->whereIn('action', RsbsaWorkflow::ACTIONS)->name('rsbsa.transition');

    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::put('/roles/permissions', [RolePermissionController::class, 'update'])
        ->middleware('can:roles.configure')->name('roles.permissions.update');
});
