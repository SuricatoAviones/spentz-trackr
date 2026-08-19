<?php

use App\Http\Controllers\AjustesController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PaymentSourceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('ajustes', AjustesController::class)->name('ajustes');

    Route::resource('expenses', ExpenseController::class)->except(['create', 'edit', 'show'])->names([
        'index' => 'expenses.index',
        'store' => 'expenses.store',
        'update' => 'expenses.update',
        'destroy' => 'expenses.destroy',
    ]);
    Route::get('expenses/nuevo', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('expenses/{expense}/editar', [ExpenseController::class, 'edit'])->name('expenses.edit');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('sources', PaymentSourceController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::put('exchange-rate', [ExchangeRateController::class, 'update'])->name('exchange-rate.update');
    Route::post('exchange-rate/sync', [ExchangeRateController::class, 'sync'])->name('exchange-rate.sync');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
