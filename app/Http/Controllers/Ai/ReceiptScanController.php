<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\ScanReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ScanReceiptRequest;
use Illuminate\Http\JsonResponse;

class ReceiptScanController extends Controller
{
    /**
     * Lee una factura y devuelve el borrador del gasto. No guarda nada: el
     * formulario se rellena y el usuario confirma.
     */
    public function __invoke(ScanReceiptRequest $request, ScanReceiptAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request->user(), $request->file('file')),
        ]);
    }
}
