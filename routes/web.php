<?php

use App\Http\Controllers\AjustesController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentSourceController;
use App\Http\Controllers\RecurringPaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Middleware\EnsureTrackingFeature;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::post('language', [LanguageController::class, 'update'])->name('language.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('ajustes', AjustesController::class)->name('ajustes');
    Route::put('commission-preferences', [AjustesController::class, 'updateCommissions'])->name('commission-preferences.update');
    Route::put('tracking-preferences', [AjustesController::class, 'updateTracking'])->name('tracking-preferences.update');
    Route::put('budget-preference', [AjustesController::class, 'updateMonthlyBudget'])->name('budget-preference.update');

    Route::middleware([EnsureTrackingFeature::class.':incomes'])->group(function () {
        Route::resource('incomes', IncomeController::class)->except(['create', 'edit', 'show'])->names([
            'index' => 'incomes.index',
            'store' => 'incomes.store',
            'update' => 'incomes.update',
            'destroy' => 'incomes.destroy',
        ]);
        Route::get('incomes/nuevo', [IncomeController::class, 'create'])->name('incomes.create');
        Route::get('incomes/{income}', [IncomeController::class, 'show'])->name('incomes.show');
        Route::get('incomes/{income}/editar', [IncomeController::class, 'edit'])->name('incomes.edit');
    });

    Route::middleware([EnsureTrackingFeature::class.':expenses'])->group(function () {
        Route::resource('expenses', ExpenseController::class)->except(['create', 'edit', 'show'])->names([
            'index' => 'expenses.index',
            'store' => 'expenses.store',
            'update' => 'expenses.update',
            'destroy' => 'expenses.destroy',
        ]);
        Route::get('expenses/nuevo', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::get('expenses/{expense}/editar', [ExpenseController::class, 'edit'])->name('expenses.edit');
    });

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('sources', PaymentSourceController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::put('exchange-rate', [ExchangeRateController::class, 'update'])->name('exchange-rate.update');
    Route::post('exchange-rate/sync', [ExchangeRateController::class, 'sync'])->name('exchange-rate.sync');

    Route::resource('savings-goals', SavingsGoalController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('savings-goals/{goal}/contributions', [SavingsGoalController::class, 'storeContribution'])->name('savings-goals.contributions.store');
    Route::delete('savings-goals/{goal}/contributions/{contribution}', [SavingsGoalController::class, 'destroyContribution'])->name('savings-goals.contributions.destroy');

    Route::resource('recurring-payments', RecurringPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('recurring-payments/{recurring_payment}/pay', [RecurringPaymentController::class, 'markPaid'])->name('recurring-payments.pay');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/install.php';
