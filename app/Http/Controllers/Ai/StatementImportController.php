<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\ScanStatementAction;
use App\Actions\Transactions\ImportTransactionsAction;
use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ScanStatementRequest;
use App\Http\Requests\ImportTransactionsRequest;
use App\Models\Category;
use App\Models\PaymentSource;
use App\Support\Presenters\AiSettingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importar movimientos desde un estado de cuenta: leer (IA, JSON), revisar
 * (en el navegador) y confirmar (crea los registros).
 */
class StatementImportController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ai/import', [
            'capabilities' => AiSettingPresenter::scanCapabilities($user->aiSetting),
            'categories' => Category::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color', 'type'])
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'type' => $category->type === CategoryType::Income ? 'income' : 'expense',
                ])
                ->all(),
            'sources' => PaymentSource::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color'])
                ->map(fn (PaymentSource $source): array => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'icon' => $source->icon,
                    'color' => $source->color,
                ])
                ->all(),
            'trackingType' => $user->tracking_type,
            'limits' => [
                'max_files' => (int) config('ai.import.max_files', 5),
                'max_rows' => (int) config('ai.import.max_rows', 200),
            ],
        ]);
    }

    public function scan(ScanStatementRequest $request, ScanStatementAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request->user(), $request->uploads()),
        ]);
    }

    public function store(ImportTransactionsRequest $request, ImportTransactionsAction $action): RedirectResponse
    {
        $counts = $action->handle($request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.import_done', ['expenses' => $counts['expenses'], 'incomes' => $counts['incomes']]),
        ]);

        return $counts['expenses'] > 0 || $counts['incomes'] === 0
            ? redirect()->route('expenses.index')
            : redirect()->route('incomes.index');
    }
}
