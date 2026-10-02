<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;
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

    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::put('/roles/permissions', [RolePermissionController::class, 'update'])
        ->middleware('can:roles.configure')->name('roles.permissions.update');
});
