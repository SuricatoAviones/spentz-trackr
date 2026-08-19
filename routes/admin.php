<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\PaymentSourceController;
use App\Http\Controllers\Admin\RateController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify-email');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::get('expenses/receipts/{receipt}', [ExpenseController::class, 'receipt'])->name('expenses.receipts.show');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('rates', [RateController::class, 'index'])->name('rates.index');
    Route::put('rates', [RateController::class, 'update'])->name('rates.update');
    Route::post('rates/sync', [RateController::class, 'sync'])->name('rates.sync');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('sources', [PaymentSourceController::class, 'index'])->name('sources.index');
    Route::patch('sources/{source}', [PaymentSourceController::class, 'update'])->name('sources.update');
    Route::delete('sources/{source}', [PaymentSourceController::class, 'destroy'])->name('sources.destroy');

    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

    Route::get('system', [SystemController::class, 'index'])->name('system.index');
    Route::post('backup', [SystemController::class, 'backup'])->name('system.backup');
});
