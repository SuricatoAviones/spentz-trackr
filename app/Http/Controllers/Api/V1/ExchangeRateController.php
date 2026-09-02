<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends BaseApiController
{
    /**
     * Return today's rates for the user (manual override, BCV, paralelo).
     */
    public function ratesForUser(Request $request, ExchangeRateService $service): JsonResponse
    {
        return $this->apiResponse([
            'rates' => $service->ratesForUser($request->user()),
        ]);
    }

    /**
     * Persist a manual rate override for today.
     */
    public function saveManualRate(Request $request, ExchangeRateService $service): JsonResponse
    {
        $validated = $request->validate([
            'rate' => ['required', 'numeric', 'min:0.01'],
        ]);

        $rate = $service->saveManualRate($request->user(), (float) $validated['rate']);

        return $this->apiResponse([
            'id' => $rate->id,
            'rate' => $rate->rate,
        ], __('messages.rate_updated'));
    }

    /**
     * Fetch and store the latest rates from the external API.
     */
    public function sync(Request $request, ExchangeRateService $service): JsonResponse
    {
        if ($service->syncFromApi() !== null) {
            return $this->apiResponse(null, __('messages.rates_synced'));
        }

        return $this->apiError(__('messages.rates_sync_failed'), 503);
    }
}
