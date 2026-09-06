<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SystemController extends Controller
{
    public function index(Request $request): Response
    {
        $databaseOk = true;

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $databaseOk = false;
        }

        return Inertia::render('admin/system', [
            'status' => [
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => config('database.default'),
                'database_ok' => $databaseOk,
                'cache_driver' => config('cache.default'),
                'storage_writable' => is_writable(storage_path()),
            ],
            'counts' => [
                'users' => User::count(),
                'expenses' => Expense::count(),
                'receipts' => ExpenseReceipt::count(),
                'exchange_rates' => ExchangeRate::count(),
                'categories' => Category::count(),
                'payment_sources' => PaymentSource::count(),
            ],
        ]);
    }

    public function backup(Request $request): StreamedResponse
    {
        AdminAction::record('backup.generated');

        $filename = 'spent_trackr_backup_'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function (): void {
            $payload = [
                'exported_at' => now()->toISOString(),
                'users' => User::query()->get()
                    ->map(fn (User $user) => collect($user->toArray())->except([
                        'password',
                        'two_factor_secret',
                        'two_factor_recovery_codes',
                        'remember_token',
                    ])->all())
                    ->all(),
                'categories' => Category::query()->get()->toArray(),
                'payment_sources' => PaymentSource::query()->get()->toArray(),
                'exchange_rates' => ExchangeRate::query()->get()->toArray(),
                'expenses' => Expense::query()->get()->toArray(),
                'receipts' => ExpenseReceipt::query()->get()->toArray(),
            ];

            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
