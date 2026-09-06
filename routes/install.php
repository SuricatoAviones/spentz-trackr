<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->middleware('throttle:install')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('/app', [InstallController::class, 'appSetup'])->name('app');
    Route::post('/execute', [InstallController::class, 'execute'])->name('execute');
    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});
