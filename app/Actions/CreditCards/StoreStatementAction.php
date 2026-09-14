<?php

namespace App\Actions\CreditCards;

use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\CreditCardStatement;
use App\Services\CreditCardCycleService;
use App\Services\ExpenseConversionService;
use Illuminate\Support\Facades\Date;

/**
 * Registra un corte: el saldo que dice el banco en la fecha de cierre.
 *
 * Es el ancla de todo el módulo — a partir de aquí la proyección solo suma
 * gastos y resta abonos. El importe se congela en USD/USDT al escribirlo, como
 * el resto de la app (ADR-001): un corte de hace seis meses no se recalcula con
 * la tasa de hoy.
 */
class StoreStatementAction
{
    public function __construct(
        private readonly ExpenseConversionService $converter,
        private readonly CreditCardCycleService $cycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CreditCard $card, array $data): CreditCardStatement
    {
        $cutDate = Date::parse($data['cut_date'])->startOfDay();

        // El corte hereda la moneda de la tarjeta: el banco no emite el estado
        // de cuenta en una moneda distinta a la de la línea de crédito.
        $converted = $this->converter->convert(
            $card->currency,
            (float) $data['closing_balance'],
            $card->currency === Currency::Ves ? (float) ($data['exchange_rate'] ?? 0) : null,
        );

        return $card->statements()->create([
            'cut_date' => $cutDate,
            // Si no la mandan, se deduce del día de pago configurado.
            'due_date' => isset($data['due_date'])
                ? Date::parse($data['due_date'])->startOfDay()
                : $this->cycle->dueDateFor($card, $cutDate),
            'closing_balance' => round((float) $data['closing_balance'], 2),
            'minimum_payment' => isset($data['minimum_payment'])
                ? round((float) $data['minimum_payment'], 2)
                : null,
            'currency' => $card->currency,
            'exchange_rate' => $card->currency === Currency::Ves ? $data['exchange_rate'] : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
            'note' => $data['note'] ?? null,
        ]);
    }
}
