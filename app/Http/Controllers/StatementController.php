<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatementRequest;
use App\Models\AiSetting;
use App\Services\AccountStatementService;
use App\Support\CsvExporter;
use App\Support\Features;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Estado de cuenta de un periodo: página imprimible y export CSV. Las cifras
 * salen de `AccountStatementService`, el mismo que lee el asistente de IA.
 */
class StatementController extends Controller
{
    public function index(StatementRequest $request, AccountStatementService $statements): Response
    {
        $user = $request->user();
        $setting = $user->aiSetting;

        return Inertia::render('reports/statement', [
            'statement' => $statements->build($user, $request->from(), $request->to()),
            'aiAvailable' => Features::aiEnabled() && $setting instanceof AiSetting && $setting->isReady(),
        ]);
    }

    public function export(StatementRequest $request, AccountStatementService $statements): StreamedResponse
    {
        $statement = $statements->build($request->user(), $request->from(), $request->to(), PHP_INT_MAX);

        $filename = 'estado_de_cuenta_'.$statement['period']['from'].'_'.$statement['period']['to'].'.csv';

        $columns = [
            'fecha', 'tipo', 'descripcion', 'categoria', 'origen', 'moneda', 'monto', 'equivalente_usd', 'neto_acumulado_usd',
        ];

        return CsvExporter::download($filename, $columns, function (\Closure $writeRow) use ($statement): void {
            foreach ($statement['movements'] as $movement) {
                $writeRow([
                    $movement['date'],
                    $movement['type'] === 'income' ? __('messages.statement_income') : __('messages.statement_expense'),
                    CsvExporter::cell((string) $movement['description']),
                    CsvExporter::cell((string) ($movement['category'] ?? '')),
                    CsvExporter::cell((string) ($movement['source'] ?? '')),
                    $movement['currency'],
                    number_format((float) $movement['amount'], 2, ',', '.'),
                    number_format((float) $movement['usd_amount'], 2, ',', '.'),
                    number_format((float) $movement['running_usd'], 2, ',', '.'),
                ]);
            }
        });
    }
}
