<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CabinetUserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::get('/cabinet/users', [CabinetUserController::class, 'index'])->name('cabinet.users.index');
    Route::post('/cabinet/users', [CabinetUserController::class, 'store'])->name('cabinet.users.store');
    Route::put('/cabinet/users/{user}', [CabinetUserController::class, 'update'])->name('cabinet.users.update');
});
