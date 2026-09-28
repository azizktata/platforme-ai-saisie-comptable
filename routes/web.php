<?php

use App\Http\Controllers\AccountingDataController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CabinetActivityController;
use App\Http\Controllers\CabinetSettingsController;
use App\Http\Controllers\CabinetUserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\RegisteredCabinetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredCabinetController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredCabinetController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::get('/accounting-data', [AccountingDataController::class, 'workspace'])->name('accounting-data.index');
    Route::get('/companies/{company}/accounting-data', [AccountingDataController::class, 'show'])
        ->name('companies.accounting-data');
    Route::post('/companies/{company}/accounting-data/demo', [AccountingDataController::class, 'seedDemo'])
        ->name('companies.accounting-data.demo');
    Route::get('/invoices', [InvoiceController::class, 'workspace'])->name('invoices.index');
    Route::get('/companies/{company}/invoices', [InvoiceController::class, 'index'])
        ->name('companies.invoices.index');
    Route::post('/companies/{company}/invoices/upload', [InvoiceController::class, 'upload'])
        ->name('companies.invoices.upload');
    Route::post('/companies/{company}/invoices/{invoice}/ocr/retry', [InvoiceController::class, 'retryOcr'])
        ->name('companies.invoices.ocr.retry');
    Route::post('/companies/{company}/invoices/bulk-review', [InvoiceController::class, 'bulkReview'])
        ->name('companies.invoices.bulk-review');
    Route::get('/companies/{company}/invoices/{invoice}/file', [InvoiceController::class, 'download'])
        ->name('companies.invoices.download');
    Route::get('/cabinet/settings', [CabinetSettingsController::class, 'edit'])->name('cabinet.settings.edit');
    Route::patch('/cabinet/settings', [CabinetSettingsController::class, 'update'])->name('cabinet.settings.update');
    Route::post('/cabinet/activities', [CabinetActivityController::class, 'store'])->name('cabinet.activities.store');
    Route::get('/cabinet/users', [CabinetUserController::class, 'index'])->name('cabinet.users.index');
    Route::post('/cabinet/users', [CabinetUserController::class, 'store'])->name('cabinet.users.store');
    Route::put('/cabinet/users/{user}', [CabinetUserController::class, 'update'])->name('cabinet.users.update');
});
