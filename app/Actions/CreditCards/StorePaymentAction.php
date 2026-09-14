<?php

namespace App\Actions\CreditCards;

use App\Enums\Currency;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Services\ExpenseConversionService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Registra un abono a la tarjeta.
 *
 * NO crea ningún `Expense`: pagar la tarjeta no es gastar, es mover dinero del
 * bolsillo a la deuda. Si además se registrara como gasto, el consumo se
 * contaría dos veces (al comprar y al pagar) y los informes mentirían.
 */
class StorePaymentAction
{
    public function __construct(private readonly ExpenseConversionService $converter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CreditCard $card, array $data): CreditCardPayment
    {
        return DB::transaction(function () use ($card, $data): CreditCardPayment {
            $converted = $this->converter->convert(
                $card->currency,
                (float) $data['amount'],
                $card->currency === Currency::Ves ? (float) ($data['exchange_rate'] ?? 0) : null,
            );

            $payment = $card->payments()->create([
                'credit_card_statement_id' => $data['credit_card_statement_id'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'currency' => $card->currency,
                'exchange_rate' => $card->currency === Currency::Ves ? $data['exchange_rate'] : null,
                'usd_amount' => $converted['usd_amount'],
                'usdt_amount' => $converted['usdt_amount'],
                'paid_at' => Date::parse($data['paid_at'])->startOfDay(),
                'note' => $data['note'] ?? null,
            ]);

            $this->markStatementPaid($payment);

            return $payment;
        });
    }

    /**
     * Un corte se marca pagado cuando los abonos que lo señalan cubren su
     * saldo. Pagos parciales lo dejan abierto, que es lo que hace el banco.
     */
    private function markStatementPaid(CreditCardPayment $payment): void
    {
        $statement = $payment->statement;

        if ($statement === null || $statement->paid_at !== null) {
            return;
        }

        $paid = (float) $statement->payments()->sum('amount');

        if ($paid + 0.001 >= (float) $statement->closing_balance) {
            $statement->update(['paid_at' => $payment->paid_at]);
        }
    }
}
