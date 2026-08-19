<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExchangeRateRequest;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function update(UpdateExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $service->saveManualRate($request->user(), (float) $request->validated('rate'));

        return back()->with('success', 'Tasa actualizada correctamente.');
    }

    public function sync(Request $request, ExchangeRateService $service): RedirectResponse
    {
        if ($service->syncFromApi() !== null) {
            return back()->with('success', 'Tasas sincronizadas con dolarapi.com.');
        }

        return back()->with('error', 'No se pudo sincronizar con dolarapi.com.');
    }
}
