<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    private const API_URL = 'https://ve.dolarapi.com/v1/dolares';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * Get today's available rates for the expense form: manual override, BCV and paralelo.
     *
     * @return array{bcv: string, paralelo: string, manual: string}
     */
    public function ratesForUser(?User $user): array
    {
        $today = Carbon::today()->toDateString();

        $rates = ['bcv' => '0.0000', 'paralelo' => '0.0000', 'manual' => '0.0000'];

        $apiRows = ExchangeRate::query()
            ->whereNull('user_id')
            ->where('source', 'api')
            ->whereDate('rate_date', '<=', $today)
            ->latest('rate_date')
            ->latest('id')
            ->get();

        foreach ($apiRows as $row) {
            if (array_key_exists($row->provider, $rates) && $rates[$row->provider] === '0.0000') {
                $rates[$row->provider] = $row->rate;
            }
        }

        if ($user !== null) {
            $manual = ExchangeRate::query()
                ->where('user_id', $user->id)
                ->where('source', 'manual')
                ->whereDate('rate_date', $today)
                ->latest('id')
                ->first();

            if ($manual !== null) {
                $rates['manual'] = $manual->rate;
            }
        }

        return $rates;
    }

    /**
     * Get the day's rate for a user: their manual override first, else the latest API rate.
     *
     * @return array{rate: string, source: string, provider: string, rate_date: string}
     */
    public function rateForUser(?User $user, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        return Cache::remember(
            "exchange-rate:{$user?->id}:{$date->toDateString()}",
            self::CACHE_TTL_SECONDS,
            function () use ($user, $date): array {
                if ($user !== null) {
                    $manual = ExchangeRate::query()
                        ->where('user_id', $user->id)
                        ->where('source', 'manual')
                        ->whereDate('rate_date', $date->toDateString())
                        ->latest('id')
                        ->first();

                    if ($manual !== null) {
                        return $this->toArray($manual);
                    }
                }

                $api = ExchangeRate::query()
                    ->whereNull('user_id')
                    ->where('source', 'api')
                    ->whereDate('rate_date', '<=', $date->toDateString())
                    ->latest('rate_date')
                    ->latest('id')
                    ->first();

                if ($api !== null) {
                    return $this->toArray($api);
                }

                return [
                    'rate' => '0.0000',
                    'source' => 'none',
                    'provider' => 'none',
                    'rate_date' => $date->toDateString(),
                ];
            }
        );
    }

    /**
     * Persist a manual rate override for a user on a given date.
     */
    public function saveManualRate(User $user, float $rate, ?Carbon $date = null): ExchangeRate
    {
        $date ??= Carbon::today();

        return ExchangeRate::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'rate_date' => $date->toDateString(),
                'source' => 'manual',
                'provider' => 'user',
            ],
            ['rate' => $rate]
        );
    }

    /**
     * Ensure today's API rate is stored, fetching it from dolarapi.com when
     * missing or stale. Manual overrides are never touched.
     *
     * @return array<string, mixed>|null The API payload when synced, or null when skipped or failed.
     */
    public function ensureFreshRate(?User $user): ?array
    {
        $today = Carbon::today()->toDateString();

        if ($user !== null) {
            $hasManualToday = ExchangeRate::query()
                ->where('user_id', $user->id)
                ->where('source', 'manual')
                ->whereDate('rate_date', $today)
                ->exists();

            if ($hasManualToday) {
                return null;
            }
        }

        $hasApiToday = ExchangeRate::query()
            ->whereNull('user_id')
            ->where('source', 'api')
            ->whereDate('rate_date', $today)
            ->exists();

        if ($hasApiToday) {
            return null;
        }

        return $this->syncFromApi();
    }

    /**
     * Fetch the latest rates from dolarapi.com and persist them.
     *
     * @return array<string, mixed>|null The API payload, or null on failure.
     */
    public function syncFromApi(?Carbon $date = null): ?array
    {
        $date ??= Carbon::today();

        try {
            $response = Http::timeout(15)->get(self::API_URL);

            if ($response->failed()) {
                report('dolarapi.com respondió con estado '.$response->status());

                return null;
            }

            $payload = $response->json();
            $rates = $this->extractUsdRates($payload);

            if ($rates === null) {
                report('dolarapi.com devolvió un payload inesperado.');

                return null;
            }

            foreach ($rates as $provider => $rate) {
                ExchangeRate::query()->updateOrCreate(
                    [
                        'user_id' => null,
                        'rate_date' => $date->toDateString(),
                        'source' => 'api',
                        'provider' => $provider,
                    ],
                    ['rate' => $rate]
                );
            }

            Cache::forget("exchange-rate:0:{$date->toDateString()}");

            return $payload;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Extract the USD rates from the dolarapi.com payload, supporting both the
     * current list format and the legacy associative format.
     *
     * @return array<string, float>|null provider => rate
     */
    private function extractUsdRates(mixed $payload): ?array
    {
        if (is_array($payload) && isset($payload['usd']['bcv'])) {
            return $this->normalizeProviderRates($payload['usd']);
        }

        if (is_array($payload) && array_is_list($payload)) {
            $rates = [];

            foreach ($payload as $entry) {
                if (! is_array($entry) || ($entry['moneda'] ?? null) !== 'USD') {
                    continue;
                }

                $provider = ($entry['fuente'] ?? null) === 'oficial'
                    ? 'bcv'
                    : ($entry['fuente'] ?? null);

                $rate = $entry['promedio'] ?? $entry['compra'] ?? $entry['venta'] ?? null;

                if ($provider !== null && is_numeric($rate)) {
                    $rates[$provider] = (float) $rate;
                }
            }

            return $rates === [] ? null : $rates;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $usd
     * @return array<string, float>|null
     */
    private function normalizeProviderRates(array $usd): ?array
    {
        $rates = [];

        foreach (['bcv', 'paralelo'] as $provider) {
            if (isset($usd[$provider]) && is_numeric($usd[$provider])) {
                $rates[$provider] = (float) $usd[$provider];
            }
        }

        return $rates === [] ? null : $rates;
    }

    /**
     * @return array{rate: string, source: string, provider: string, rate_date: string}
     */
    private function toArray(ExchangeRate $exchangeRate): array
    {
        return [
            'rate' => $exchangeRate->rate,
            'source' => $exchangeRate->source,
            'provider' => $exchangeRate->provider,
            'rate_date' => $exchangeRate->rate_date->toDateString(),
        ];
    }
}
