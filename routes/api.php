<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CreditCardController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\PaymentSourceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RecurringPaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavingsGoalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned API under /api/v1. Protected endpoints use Sanctum personal
| access tokens via auth:sanctum. Rate limiting is defined in
| AppServiceProvider.configureRateLimiters.
|
*/

Route::middleware('throttle:api.auth')->group(function () {
    Route::post('auth/register', [AuthenticatedSessionController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])->name('api.v1.auth.login');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::delete('auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.v1.auth.logout');
    Route::get('auth/me', [AuthenticatedSessionController::class, 'me'])->name('api.v1.auth.me');

    Route::put('user/profile', [ProfileController::class, 'update'])->name('api.v1.user.profile');
    Route::put('user/locale', [ProfileController::class, 'updateLocale'])->name('api.v1.user.locale');

    // Expenses
    Route::apiResource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->names([
            'index' => 'api.v1.expenses.index',
            'store' => 'api.v1.expenses.store',
            'show' => 'api.v1.expenses.show',
            'update' => 'api.v1.expenses.update',
            'destroy' => 'api.v1.expenses.destroy',
        ]);

    // Incomes
    Route::apiResource('incomes', IncomeController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->names([
            'index' => 'api.v1.incomes.index',
            'store' => 'api.v1.incomes.store',
            'show' => 'api.v1.incomes.show',
            'update' => 'api.v1.incomes.update',
            'destroy' => 'api.v1.incomes.destroy',
        ]);

    // Categories
    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index' => 'api.v1.categories.index',
            'store' => 'api.v1.categories.store',
            'update' => 'api.v1.categories.update',
            'destroy' => 'api.v1.categories.destroy',
        ]);

    // Payment Sources
    Route::apiResource('sources', PaymentSourceController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names([
            'index' => 'api.v1.sources.index',
            'store' => 'api.v1.sources.store',
            'update' => 'api.v1.sources.update',
            'destroy' => 'api.v1.sources.destroy',
        ]);

    // Savings Goals
    Route::apiResource('savings-goals', SavingsGoalController::class)
        ->names([
            'index' => 'api.v1.savings-goals.index',
            'store' => 'api.v1.savings-goals.store',
            'show' => 'api.v1.savings-goals.show',
            'update' => 'api.v1.savings-goals.update',
            'destroy' => 'api.v1.savings-goals.destroy',
        ]);
    Route::post('savings-goals/{savings_goal}/contributions', [SavingsGoalController::class, 'storeContribution'])
        ->name('api.v1.savings-goals.contributions.store');
    Route::delete('savings-goals/{savings_goal}/contributions/{contribution}', [SavingsGoalController::class, 'destroyContribution'])
        ->name('api.v1.savings-goals.contributions.destroy');

    // Recurring Payments
    Route::apiResource('recurring-payments', RecurringPaymentController::class)
        ->names([
            'index' => 'api.v1.recurring-payments.index',
            'store' => 'api.v1.recurring-payments.store',
            'show' => 'api.v1.recurring-payments.show',
            'update' => 'api.v1.recurring-payments.update',
            'destroy' => 'api.v1.recurring-payments.destroy',
        ]);
    Route::post('recurring-payments/{recurring_payment}/pay', [RecurringPaymentController::class, 'markPaid'])
        ->name('api.v1.recurring-payments.pay');

    // Credit Cards. El parámetro tiene que llamarse {credit_card}: los Form
    // Requests de corte y abono leen la tarjeta con $this->route('credit_card')
    // para exigir la tasa en tarjetas en Bs y comprobar que el corte es suyo.
    Route::apiResource('credit-cards', CreditCardController::class)
        ->names([
            'index' => 'api.v1.credit-cards.index',
            'store' => 'api.v1.credit-cards.store',
            'show' => 'api.v1.credit-cards.show',
            'update' => 'api.v1.credit-cards.update',
            'destroy' => 'api.v1.credit-cards.destroy',
        ]);
    Route::post('credit-cards/{credit_card}/statements', [CreditCardController::class, 'storeStatement'])
        ->name('api.v1.credit-cards.statements.store');
    Route::delete('credit-cards/{credit_card}/statements/{statement}', [CreditCardController::class, 'destroyStatement'])
        ->name('api.v1.credit-cards.statements.destroy');
    Route::post('credit-cards/{credit_card}/payments', [CreditCardController::class, 'storePayment'])
        ->name('api.v1.credit-cards.payments.store');
    Route::delete('credit-cards/{credit_card}/payments/{payment}', [CreditCardController::class, 'destroyPayment'])
        ->name('api.v1.credit-cards.payments.destroy');

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('api.v1.dashboard');

    // Exchange Rates
    Route::get('rates', [ExchangeRateController::class, 'ratesForUser'])->name('api.v1.rates.index');
    Route::post('rates/sync', [ExchangeRateController::class, 'sync'])
        ->middleware('throttle:rates.sync')
        ->name('api.v1.rates.sync');
    Route::put('rates', [ExchangeRateController::class, 'saveManualRate'])->name('api.v1.rates.update');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('api.v1.reports.index');
    Route::get('reports/monthly-summary', [ReportController::class, 'monthlySummary'])->name('api.v1.reports.monthly-summary');
});
