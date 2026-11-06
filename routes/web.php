<?php

use App\Http\Controllers\Ai\AiSettingsController;
use App\Http\Controllers\Ai\AssistantController;
use App\Http\Controllers\Ai\ReceiptScanController;
use App\Http\Controllers\Ai\StatementImportController;
use App\Http\Controllers\AjustesController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\PaymentSourceController;
use App\Http\Controllers\RecurringPaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\StatementController;
use App\Http\Middleware\EnsureAiEnabled;
use App\Http\Middleware\EnsureTrackingFeature;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public: the browser fetches it before (and without) authentication.
Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');

// Públicas a propósito: hay que poder leerlas antes de crear una cuenta.
Route::get('terminos', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');

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
        Route::get('incomes/{income}/receipts/{receipt}', [IncomeController::class, 'receipt'])->name('incomes.receipts.show');
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
        Route::get('expenses/{expense}/receipts/{receipt}', [ExpenseController::class, 'receipt'])->name('expenses.receipts.show');
    });

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('sources', PaymentSourceController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/estado-de-cuenta', [StatementController::class, 'index'])->name('reports.statement');
    Route::get('reports/estado-de-cuenta/export', [StatementController::class, 'export'])->name('reports.statement.export');

    Route::put('exchange-rate', [ExchangeRateController::class, 'update'])->name('exchange-rate.update');
    Route::post('exchange-rate/sync', [ExchangeRateController::class, 'sync'])
        ->middleware('throttle:rates.sync')
        ->name('exchange-rate.sync');

    Route::resource('savings-goals', SavingsGoalController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('savings-goals/{goal}/contributions', [SavingsGoalController::class, 'storeContribution'])->name('savings-goals.contributions.store');
    Route::delete('savings-goals/{goal}/contributions/{contribution}', [SavingsGoalController::class, 'destroyContribution'])->name('savings-goals.contributions.destroy');

    // Tarjetas de crédito. Cada tarjeta posee un PaymentSource, así que sus
    // consumos son gastos normales y no hay movimientos duplicados.
    Route::resource('credit-cards', CreditCardController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('credit-cards/{credit_card}/statements', [CreditCardController::class, 'storeStatement'])->name('credit-cards.statements.store');
    Route::delete('credit-cards/{credit_card}/statements/{statement}', [CreditCardController::class, 'destroyStatement'])->name('credit-cards.statements.destroy');
    Route::post('credit-cards/{credit_card}/payments', [CreditCardController::class, 'storePayment'])->name('credit-cards.payments.store');
    Route::delete('credit-cards/{credit_card}/payments/{payment}', [CreditCardController::class, 'destroyPayment'])->name('credit-cards.payments.destroy');

    Route::resource('recurring-payments', RecurringPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('recurring-payments/{recurring_payment}/pay', [RecurringPaymentController::class, 'markPaid'])->name('recurring-payments.pay');

    // Asistente de IA. Cada usuario conecta su propio proveedor y clave; las
    // rutas que llaman al proveedor llevan su propio limitador (`ai`).
    Route::middleware(EnsureAiEnabled::class)->group(function () {
        Route::get('ia/ajustes', [AiSettingsController::class, 'edit'])->name('ai.settings.edit');
        Route::put('ia/ajustes', [AiSettingsController::class, 'update'])->name('ai.settings.update');
        Route::delete('ia/ajustes', [AiSettingsController::class, 'destroy'])->name('ai.settings.destroy');
        Route::get('asistente', [AssistantController::class, 'index'])->name('ai.assistant');
        Route::get('ia/importar', [StatementImportController::class, 'create'])->name('ai.import.create');
        // Confirmar no llama al proveedor (crea los registros revisados), así
        // que no gasta del limitador `ai`.
        Route::post('ia/importar', [StatementImportController::class, 'store'])->name('ai.import.store');

        Route::middleware('throttle:ai')->group(function () {
            Route::post('ia/ajustes/probar', [AiSettingsController::class, 'test'])->name('ai.settings.test');
            Route::post('ia/facturas/leer', ReceiptScanController::class)->name('ai.receipts.scan');
            Route::post('asistente/mensajes', [AssistantController::class, 'ask'])->name('ai.assistant.ask');
            Route::post('ia/estado-de-cuenta/analizar', [AssistantController::class, 'analyzeStatement'])->name('ai.statement.analyze');
            Route::post('ia/importar/leer', [StatementImportController::class, 'scan'])->name('ai.import.scan');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
