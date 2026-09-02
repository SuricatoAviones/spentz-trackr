<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminRateRequest;
use App\Models\AdminAction;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RateController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();

        $apiRates = ExchangeRate::query()
            ->whereNull('user_id')
            ->where('source', 'api')
            ->whereDate('rate_date', $today)
            ->get(['provider', 'rate'])
            ->keyBy('provider');

        $manualToday = ExchangeRate::query()
            ->where('source', 'manual')
            ->whereDate('rate_date', $today)
            ->with('user:id,name,email')
            ->latest('id')
            ->get()
            ->map(fn (ExchangeRate $rate) => [
                'id' => $rate->id,
                'rate' => $rate->rate,
                'user' => [
                    'id' => $rate->user->id,
                    'name' => $rate->user->name,
                    'email' => $rate->user->email,
                ],
            ]);

        $history = ExchangeRate::query()
            ->with('user:id,name')
            ->latest('rate_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ExchangeRate $rate) => [
                'id' => $rate->id,
                'rate' => $rate->rate,
                'provider' => $rate->provider,
                'source' => $rate->source,
                'rate_date' => $rate->rate_date->toDateString(),
                'user_name' => $rate->user?->name,
            ]);

        return Inertia::render('admin/rates/index', [
            'today' => [
                'date' => $today,
                'bcv' => $apiRates->get('bcv')?->rate,
                'paralelo' => $apiRates->get('paralelo')?->rate,
            ],
            'manualToday' => $manualToday,
            'history' => $history,
        ]);
    }

    public function update(UpdateAdminRateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $today = Carbon::today()->toDateString();

        foreach (['bcv', 'paralelo'] as $provider) {
            if (! isset($validated[$provider])) {
                continue;
            }

            ExchangeRate::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'rate_date' => $today,
                    'source' => 'api',
                    'provider' => $provider,
                ],
                ['rate' => $validated[$provider]],
            );
        }

        Cache::forget("exchange-rate:0:{$today}");

        AdminAction::record('rate.updated', null, ['rate_date' => $today]);

        return back()->with('success', __('messages.rate_day_updated'));
    }

    public function sync(ExchangeRateService $service): RedirectResponse
    {
        AdminAction::record('rate.synced');

        if ($service->syncFromApi() !== null) {
            return back()->with('success', __('messages.rates_synced'));
        }

        return back()->with('error', __('messages.rates_sync_failed'));
    }
}
