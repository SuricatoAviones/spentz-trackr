<?php

namespace App\Actions\Transactions;

use App\Actions\Expenses\StoreExpenseAction;
use App\Actions\Incomes\StoreIncomeAction;
use App\Enums\Currency;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra de una vez los movimientos que el usuario confirmó de un estado de
 * cuenta.
 *
 * Cada fila pasa por `StoreExpenseAction` / `StoreIncomeAction`, igual que si
 * se tecleara a mano: no hay un camino paralelo que congele distinto. Lo único
 * propio de la importación es la tasa: una fila en Bs sin tasa toma la del día
 * del movimiento, nunca la de hoy (ADR-001).
 *
 * Todo o nada: si una fila falla, no se importa ninguna, para que el usuario
 * no tenga que adivinar cuáles entraron.
 */
class ImportTransactionsAction
{
    public function __construct(
        private readonly StoreExpenseAction $expenses,
        private readonly StoreIncomeAction $incomes,
        private readonly ExchangeRateService $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validado por ImportTransactionsRequest
     * @return array{expenses: int, incomes: int}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $counts = ['expenses' => 0, 'incomes' => 0];

            foreach ($data['rows'] as $index => $row) {
                $currency = Currency::from($row['currency']);
                [$rate, $provider] = $this->rate($user, $currency, $row, (int) $index);
                $isVes = $currency === Currency::Ves;

                if ($row['type'] === 'expense') {
                    $this->expenses->handle($user, [
                        'category_id' => (int) $row['category_id'],
                        'payment_source_id' => (int) $data['payment_source_id'],
                        'currency' => $currency->value,
                        'payment_method' => $isVes ? ($row['payment_method'] ?? null) : null,
                        'commission' => null,
                        'amount' => $row['amount'],
                        'exchange_rate' => $rate,
                        'rate_provider' => $provider,
                        'description' => $row['description'],
                        'note' => $row['note'] ?? null,
                        'spent_at' => $row['date'],
                    ]);

                    $counts['expenses']++;

                    continue;
                }

                $this->incomes->handle($user, [
                    'category_id' => (int) $row['category_id'],
                    'currency' => $currency->value,
                    'amount' => $row['amount'],
                    'exchange_rate' => $rate,
                    'rate_provider' => $provider,
                    'description' => $row['description'],
                    'note' => $row['note'] ?? null,
                    'received_at' => $row['date'],
                ]);

                $counts['incomes']++;
            }

            return $counts;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: float|null, 1: string|null}
     */
    private function rate(User $user, Currency $currency, array $row, int $index): array
    {
        if ($currency !== Currency::Ves) {
            return [null, null];
        }

        if (isset($row['exchange_rate']) && (float) $row['exchange_rate'] > 0) {
            return [(float) $row['exchange_rate'], $row['rate_provider'] ?? 'custom'];
        }

        $dayRate = $this->rates->rateForUser($user, Date::parse($row['date']));

        if ((float) $dayRate['rate'] <= 0) {
            throw ValidationException::withMessages([
                "rows.{$index}.exchange_rate" => __('validation.app.import_no_rate', ['date' => $row['date']]),
            ]);
        }

        return [(float) $dayRate['rate'], $dayRate['provider'] !== 'none' ? $dayRate['provider'] : null];
    }
}
