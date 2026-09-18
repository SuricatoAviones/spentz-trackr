<?php

namespace App\Support\Presenters;

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;

/**
 * Forma JSON única de una meta de ahorro. Ver la regla del presenter en
 * `.ai/rules/controllers.md`: web y API consumen esto, nunca el modelo crudo.
 */
final class SavingsGoalPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(SavingsGoal $goal): array
    {
        $saved = self::saved($goal);
        $target = (float) $goal->target_usd_amount;
        $percent = $target > 0 ? min(100, round(($saved / $target) * 100)) : 0;

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => $goal->target_amount,
            'currency' => $goal->currency->value,
            'exchange_rate' => $goal->exchange_rate,
            'target_usd_amount' => $goal->target_usd_amount,
            'icon' => $goal->icon,
            'color' => $goal->color,
            'deadline' => $goal->deadline?->toDateString(),
            'note' => $goal->note,
            'achieved_at' => $goal->achieved_at?->toIso8601String(),
            'saved' => $saved,
            'percent' => $percent,
            'contributions' => $goal->contributions
                ->map(fn (SavingsContribution $contribution): array => [
                    'id' => $contribution->id,
                    'amount' => $contribution->amount,
                    'currency' => $contribution->currency->value,
                    'usd_amount' => $contribution->usd_amount,
                    'income_id' => $contribution->income_id,
                    'contributed_at' => $contribution->contributed_at->toDateString(),
                    'note' => $contribution->note,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * El listado precalcula el total con `withSum('contributions as saved_usd')`;
     * fuera de él (crear, ver una meta suelta) se suma la relación cargada, para
     * que el presenter no dependa de que quien llame recuerde el agregado.
     */
    private static function saved(SavingsGoal $goal): float
    {
        $aggregate = $goal->getAttribute('saved_usd');

        if ($aggregate !== null) {
            return round((float) $aggregate, 2);
        }

        return round((float) $goal->contributions->sum('usd_amount'), 2);
    }
}
