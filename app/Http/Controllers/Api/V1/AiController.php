<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\AnalyzeStatementAction;
use App\Actions\Ai\AskAssistantAction;
use App\Actions\Ai\ScanReceiptAction;
use App\Actions\Ai\ScanStatementAction;
use App\Actions\Ai\UpdateAiSettingsAction;
use App\Actions\Ai\VerifyAiConnectionAction;
use App\Actions\Transactions\ImportTransactionsAction;
use App\Http\Requests\Ai\AskAssistantRequest;
use App\Http\Requests\Ai\ScanReceiptRequest;
use App\Http\Requests\Ai\ScanStatementRequest;
use App\Http\Requests\Ai\UpdateAiSettingsRequest;
use App\Http\Requests\ImportTransactionsRequest;
use App\Http\Requests\StatementRequest;
use App\Support\Presenters\AiSettingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Asistente de IA por API. Comparte Actions, Form Requests y Presenter con la
 * web (ver `.ai/rules/routes.md`): ninguna regla se repite aquí.
 *
 * Los errores del proveedor salen como `{success: false, message, reason}`
 * con un código acorde: 422 (clave o modelo mal configurados), 429 (cuota del
 * proveedor agotada), 502 (el proveedor falló) o 504 (no respondió a tiempo).
 */
class AiController extends BaseApiController
{
    /**
     * Configuración de IA del usuario y catálogo de proveedores. Nunca incluye la clave.
     */
    public function settings(Request $request): JsonResponse
    {
        return $this->apiResponse([
            'setting' => AiSettingPresenter::present($request->user()->aiSetting),
            'providers' => AiSettingPresenter::providers(),
        ]);
    }

    /**
     * Guarda proveedor, modelo y clave. Una clave vacía conserva la guardada si no cambia el proveedor.
     */
    public function updateSettings(UpdateAiSettingsRequest $request, UpdateAiSettingsAction $action): JsonResponse
    {
        $setting = $action->handle($request->user(), $request->validated());

        return $this->apiResponse(AiSettingPresenter::present($setting), __('messages.ai_settings_saved'));
    }

    /**
     * Desconecta el proveedor y borra la clave guardada.
     */
    public function destroySettings(Request $request): JsonResponse
    {
        $request->user()->aiSetting()->delete();

        return $this->apiResponse(null, __('messages.ai_settings_deleted'));
    }

    /**
     * Comprueba que la clave y el modelo responden.
     */
    public function test(Request $request, VerifyAiConnectionAction $action): JsonResponse
    {
        $setting = $action->handle($request->user());

        return $this->apiResponse(AiSettingPresenter::present($setting), __('messages.ai_connection_ok'));
    }

    /**
     * Lee una factura o comprobante (imagen o PDF) y devuelve un borrador de gasto.
     *
     * No crea el gasto: el cliente muestra el borrador y lo guarda con `POST /expenses`.
     */
    public function scanReceipt(ScanReceiptRequest $request, ScanReceiptAction $action): JsonResponse
    {
        return $this->apiResponse($action->handle($request->user(), $request->file('file')));
    }

    /**
     * Lee un estado de cuenta (un PDF o hasta 5 capturas en `files[]`) y devuelve sus movimientos como borradores.
     *
     * No crea nada. Cada fila trae la tasa del día del movimiento, la categoría sugerida y `duplicate_of`
     * si coincide con un registro existente. Las filas revisadas se confirman con `POST /ai/statements/import`.
     */
    public function scanStatement(ScanStatementRequest $request, ScanStatementAction $action): JsonResponse
    {
        return $this->apiResponse($action->handle($request->user(), $request->uploads()));
    }

    /**
     * Registra los movimientos confirmados de un estado de cuenta (todo o nada).
     *
     * Cada fila pasa por las mismas reglas que un gasto o ingreso creado a mano. Una fila en Bs sin
     * `exchange_rate` toma la tasa del día del movimiento.
     */
    public function importStatement(ImportTransactionsRequest $request, ImportTransactionsAction $action): JsonResponse
    {
        $counts = $action->handle($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => $counts,
            'message' => __('messages.import_done', ['expenses' => $counts['expenses'], 'incomes' => $counts['incomes']]),
        ], 201);
    }

    /**
     * Conversación con el asistente financiero. El cliente envía el historial completo.
     */
    public function ask(AskAssistantRequest $request, AskAssistantAction $action): JsonResponse
    {
        return $this->apiResponse($action->handle($request->user(), $request->history()));
    }

    /**
     * Análisis en lenguaje natural del estado de cuenta de un periodo.
     */
    public function analyzeStatement(StatementRequest $request, AnalyzeStatementAction $action): JsonResponse
    {
        return $this->apiResponse($action->handle($request->user(), $request->from(), $request->to()));
    }
}
