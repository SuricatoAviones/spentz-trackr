<?php

namespace App\Actions\SavingsGoals\Concerns;

use App\Enums\Currency;
use App\Models\SavingsGoal;

trait RefreshesAchievement
{
    /**
     * La meta se marca (o se desmarca) cumplida sola: es la suma de los aportes
     * la que manda, no un botón. Sin el camino de vuelta, borrar un aporte
     * dejaba la meta "lograda" para siempre.
     */
    protected function refreshAchievement(SavingsGoal $goal): void
    {
        $saved = (float) $goal->contributions()->sum('usd_amount');
        $target = (float) $goal->target_usd_amount;

        if ($saved >= $target && $goal->achieved_at === null) {
            $goal->update(['achieved_at' => now()]);
        } elseif ($saved < $target && $goal->achieved_at !== null) {
            $goal->update(['achieved_at' => null]);
        }
    }

    /**
     * El objetivo se congela en USD al escribirlo (ADR-001): una meta en Bs no
     * se recalcula con la tasa de hoy.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function freezeTarget(array $data): array
    {
        $currency = Currency::from($data['currency']);

        $data['exchange_rate'] = $currency === Currency::Ves ? $data['exchange_rate'] : null;
        $data['target_usd_amount'] = $currency === Currency::Ves
            ? round((float) $data['target_amount'] / (float) $data['exchange_rate'], 2)
            : round((float) $data['target_amount'], 2);

        return $data;
    }
}
