<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\User;
use App\Support\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $expenses = $this->expenseQuery($request)
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Expense $expense) => $this->shape($expense));

        $filters = $request->only(['search', 'user_id', 'category_id', 'currency', 'from', 'to']);
        $filters['search'] = $request->string('search')->toString();
        $filters['currency'] = $request->string('currency')->toString();

        return Inertia::render('admin/expenses/index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
            'categories' => Category::query()
                ->with('user:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'user_id'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'user_name' => $category->user->name,
                ])
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        AdminAction::record('expenses.exported');

        $query = $this->expenseQuery($request)->orderByDesc('spent_at');

        $filename = 'gastos_globales_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'fecha',
                'usuario',
                'email',
                'descripcion',
                'categoria',
                'origen',
                'moneda',
                'monto',
                'tasa_bs_usd',
                'tasa_fuente',
                'equivalente_usd',
                'equivalente_usdt',
                'nota',
            ]);

            $query->chunk(500, function ($expenses) use ($output): void {
                foreach ($expenses as $expense) {
                    fputcsv($output, [
                        $expense->spent_at->toDateString(),
                        $expense->user->name,
                        $expense->user->email,
                        CsvExporter::cell($expense->description),
                        CsvExporter::cell($expense->category->name),
                        CsvExporter::cell($expense->paymentSource->name),
                        $expense->currency->value,
                        number_format((float) $expense->amount, 2, ',', '.'),
                        $expense->exchange_rate !== null ? number_format((float) $expense->exchange_rate, 4, ',', '.') : '',
                        CsvExporter::cell($expense->rate_provider ?? ''),
                        number_format((float) $expense->usd_amount, 2, ',', '.'),
                        number_format((float) $expense->usdt_amount, 2, ',', '.'),
                        CsvExporter::cell($expense->note ?? ''),
                    ]);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function receipt(ExpenseReceipt $receipt): StreamedResponse
    {
        return Storage::disk('public')->response($receipt->path, $receipt->original_name);
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
            $receipt->delete();
        }

        AdminAction::record('expense.deleted', $expense);
        $expense->delete();

        return redirect()
            ->route('admin.expenses.index')
            ->with('success', __('messages.expense_deleted'));
    }

    /**
     * Build the filtered query shared by the index and the CSV export.
     */
    private function expenseQuery(Request $request): Builder
    {
        return Expense::query()
            ->with(['user:id,name,email', 'category', 'paymentSource', 'receipts'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('user_id'), function ($query, int $userId) {
                $query->where('user_id', $userId);
            })
            ->when($request->integer('category_id'), function ($query, int $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->string('currency')->toString(), function ($query, string $currency) {
                $query->where('currency', $currency);
            })
            ->when($request->string('from')->toString(), function ($query, string $from) {
                $query->whereDate('spent_at', '>=', $from);
            })
            ->when($request->string('to')->toString(), function ($query, string $to) {
                $query->whereDate('spent_at', '<=', $to);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'currency' => $expense->currency->value,
            'usd_amount' => $expense->usd_amount,
            'usdt_amount' => $expense->usdt_amount,
            'spent_at' => $expense->spent_at->toDateString(),
            'user' => [
                'id' => $expense->user->id,
                'name' => $expense->user->name,
                'email' => $expense->user->email,
            ],
            'category' => $expense->category->name,
            'source' => $expense->paymentSource->name,
            'receipts' => $expense->receipts
                ->map(fn (ExpenseReceipt $receipt) => [
                    'id' => $receipt->id,
                    'original_name' => $receipt->original_name,
                ])
                ->values()
                ->all(),
        ];
    }
}
