<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Fechas de corte/pago y saldo proyectado de una tarjeta.
 *
 * El saldo NO se inventa: el último corte es la verdad (lo dice el banco) y
 * entre cortes se proyecta sumando los gastos registrados contra el origen de
 * la tarjeta y restando los abonos. La proyección viaja marcada con
 * `is_estimate` para que la UI no la presente como saldo real.
 */
final class CreditCardCycleService
{
    /**
     * Un día concreto del mes de `$month`, recortado al último día real.
     *
     * Sin esto, una tarjeta que corta el 31 no tiene corte en febrero y el
     * cálculo se va al mes siguiente (o revienta).
     */
    public function onDay(CarbonInterface $month, int $day): CarbonInterface
    {
        $first = Date::create($month->year, $month->month, 1)->startOfDay();

        return $first->setDay(min($day, $first->daysInMonth));
    }

    /** Fecha del último corte ocurrido en o antes de `$on`. */
    public function lastCutDate(CreditCard $card, ?CarbonInterface $on = null): CarbonInterface
    {
        $on = ($on ?? Date::today())->startOfDay();
        $candidate = $this->onDay($on, $card->cut_day);

        return $candidate->lessThanOrEqualTo($on)
            ? $candidate
            : $this->onDay($on->copy()->subMonthNoOverflow(), $card->cut_day);
    }

    /** Fecha del siguiente corte estrictamente posterior a `$on`. */
    public function nextCutDate(CreditCard $card, ?CarbonInterface $on = null): CarbonInterface
    {
        $on = ($on ?? Date::today())->startOfDay();
        $candidate = $this->onDay($on, $card->cut_day);

        return $candidate->greaterThan($on)
            ? $candidate
            : $this->onDay($on->copy()->addMonthNoOverflow(), $card->cut_day);
    }

    /**
     * Vencimiento del pago de un corte.
     *
     * Si el día de pago es posterior al de corte cae en el mismo mes; si es
     * anterior o igual, en el siguiente (nadie paga el mismo día que cierra).
     */
    public function dueDateFor(CreditCard $card, CarbonInterface $cutDate): CarbonInterface
    {
        return $card->due_day > $card->cut_day
            ? $this->onDay($cutDate, $card->due_day)
            : $this->onDay($cutDate->copy()->addMonthNoOverflow(), $card->due_day);
    }

    /**
     * Situación completa de la tarjeta: ciclo vigente y saldo proyectado.
     *
     * @return array<string, mixed>
     */
    public function summary(CreditCard $card, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? Date::today())->startOfDay();

        $statement = $card->latestStatement();
        $since = $statement?->cut_date;

        $charges = $this->chargesSince($card, $since);
        $payments = $this->paymentsSince($card, $since);

        $closing = $statement !== null ? (float) $statement->closing_balance : 0.0;
        $projectedUsed = max(0.0, $closing + $charges['native'] - $payments['native']);

        $limit = (float) $card->credit_limit;

        $nextCut = $this->nextCutDate($card, $today);

        return [
            'cycle' => [
                'last_cut_date' => $this->lastCutDate($card, $today)->toDateString(),
                'next_cut_date' => $nextCut->toDateString(),
                'next_due_date' => $this->dueDateFor($card, $nextCut)->toDateString(),
                'days_to_cut' => (int) $today->diffInDays($nextCut, absolute: false),
            ],
            'balance' => [
                'closing' => $statement !== null ? round($closing, 2) : null,
                'charges_since_cut' => round($charges['native'], 2),
                'payments_since_cut' => round($payments['native'], 2),
                'projected_used' => round($projectedUsed, 2),
                'available' => round($limit - $projectedUsed, 2),
                'usage_percent' => $limit > 0 ? min(100, (int) round(($projectedUsed / $limit) * 100)) : 0,
                // Sin movimientos posteriores al corte la cifra ES la del banco.
                'is_estimate' => $charges['count'] > 0 || $payments['count'] > 0,
                // Movimientos en otra moneda que NO se suman: convertirlos con
                // la tasa de hoy falsearía un saldo que el banco lleva en su
                // propia moneda. Se cuentan para poder avisar en pantalla.
                'foreign_movements' => $charges['foreign'] + $payments['foreign'],
            ],
            'usd' => [
                'projected_used' => round(
                    ($statement !== null ? (float) $statement->usd_amount : 0.0)
                    + $charges['usd'] - $payments['usd'],
                    2,
                ),
            ],
        ];
    }

    /**
     * @return array{native: float, usd: float, count: int, foreign: int}
     */
    private function chargesSince(CreditCard $card, ?CarbonInterface $since): array
    {
        if ($card->payment_source_id === null) {
            return ['native' => 0.0, 'usd' => 0.0, 'count' => 0, 'foreign' => 0];
        }

        $rows = Expense::query()
            ->where('user_id', $card->user_id)
            ->where('payment_source_id', $card->payment_source_id)
            ->when($since !== null, fn ($q) => $q->whereDate('spent_at', '>', $since))
            ->get(['amount', 'commission', 'currency', 'usd_amount'])
            ->map(fn (Expense $expense): array => [
                // El banco carga la comisión junto con el consumo; `amount`
                // guarda solo la base (ver la regla de comisiones en Bs).
                'native' => (float) $expense->amount + (float) ($expense->commission ?? 0),
                'usd' => (float) $expense->usd_amount,
                'currency' => $expense->currency,
            ])
            ->all();

        return $this->totals(array_values($rows), $card);
    }

    /**
     * @return array{native: float, usd: float, count: int, foreign: int}
     */
    private function paymentsSince(CreditCard $card, ?CarbonInterface $since): array
    {
        $rows = CreditCardPayment::query()
            ->where('credit_card_id', $card->id)
            ->when($since !== null, fn ($q) => $q->whereDate('paid_at', '>', $since))
            ->get(['amount', 'currency', 'usd_amount'])
            ->map(fn (CreditCardPayment $payment): array => [
                'native' => (float) $payment->amount,
                'usd' => (float) $payment->usd_amount,
                'currency' => $payment->currency,
            ])
            ->all();

        return $this->totals(array_values($rows), $card);
    }

    /**
     * Suma en la moneda de la tarjeta, dejando fuera lo que esté en otra.
     *
     * @param  list<array{native: float, usd: float, currency: Currency}>  $rows
     * @return array{native: float, usd: float, count: int, foreign: int}
     */
    private function totals(array $rows, CreditCard $card): array
    {
        $native = 0.0;
        $usd = 0.0;
        $foreign = 0;

        foreach ($rows as $row) {
            $usd += $row['usd'];

            if ($row['currency'] !== $card->currency) {
                $foreign++;

                continue;
            }

            $native += $row['native'];
        }

        return [
            'native' => $native,
            'usd' => $usd,
            'count' => count($rows),
            'foreign' => $foreign,
        ];
    }
}
