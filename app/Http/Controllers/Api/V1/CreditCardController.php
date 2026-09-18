<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreditCards\DeleteCreditCardAction;
use App\Actions\CreditCards\StoreCreditCardAction;
use App\Actions\CreditCards\StorePaymentAction;
use App\Actions\CreditCards\StoreStatementAction;
use App\Actions\CreditCards\UpdateCreditCardAction;
use App\Http\Requests\StoreCreditCardPaymentRequest;
use App\Http\Requests\StoreCreditCardRequest;
use App\Http\Requests\StoreCreditCardStatementRequest;
use App\Http\Requests\UpdateCreditCardRequest;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Services\CreditCardCycleService;
use App\Support\Presenters\CreditCardPresenter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardController extends BaseApiController
{
    /**
     * Listar las tarjetas del usuario con su ciclo y saldo proyectado.
     *
     * Ojo con `balance.projected_used`: cuando `balance.is_estimate` es `true`
     * es una proyección (último corte + gastos registrados − abonos), no el
     * saldo que da el banco.
     */
    #[QueryParameter('active', description: 'Filtrar por estado: 1 solo las activas, 0 solo las archivadas.', type: 'boolean')]
    public function index(Request $request, CreditCardCycleService $cycle): JsonResponse
    {
        $cards = CreditCard::query()
            ->forUser($request->user()->id)
            ->with('paymentSource')
            ->when($request->has('active'), function ($query) use ($request) {
                $query->where('active', $request->boolean('active'));
            })
            ->orderByDesc('active')
            ->orderBy('bank')
            ->get()
            ->map(fn (CreditCard $card) => CreditCardPresenter::present($card, $cycle))
            ->all();

        return $this->apiResponse([
            'cards' => $cards,
        ]);
    }

    /**
     * Crear una tarjeta. Se crea con ella el origen de pago que la representa
     * en los gastos, así que sus consumos son gastos normales.
     */
    public function store(StoreCreditCardRequest $request, StoreCreditCardAction $action): JsonResponse
    {
        $card = $action->handle($request->user(), $request->validated());

        return $this->apiCreated($card->id, __('messages.credit_card_created'))
            ->header('Location', route('api.v1.credit-cards.show', $card));
    }

    /**
     * Ficha completa de una tarjeta: ciclo, saldo, cortes, abonos y los últimos
     * consumos cargados contra su origen de pago.
     */
    public function show(CreditCard $creditCard, CreditCardCycleService $cycle): JsonResponse
    {
        $this->authorize('view', $creditCard);

        $creditCard->load(['statements', 'payments', 'paymentSource']);

        return $this->apiResponse([
            'card' => CreditCardPresenter::detail($creditCard, $cycle),
            'recent_charges' => $this->recentCharges($creditCard),
        ]);
    }

    /**
     * Actualizar una tarjeta. El origen de pago se renombra con ella.
     */
    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard, UpdateCreditCardAction $action, CreditCardCycleService $cycle): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $action->handle($creditCard, $request->validated());

        return $this->apiResponse([
            'card' => CreditCardPresenter::present($creditCard->fresh(['paymentSource']), $cycle),
        ], __('messages.credit_card_updated'));
    }

    /**
     * Eliminar una tarjeta con sus cortes y abonos. Los gastos hechos con ella
     * se conservan: son movimientos reales del historial.
     */
    public function destroy(CreditCard $creditCard, DeleteCreditCardAction $action): JsonResponse
    {
        $this->authorize('delete', $creditCard);

        $action->handle($creditCard);

        return $this->apiResponse(['id' => $creditCard->id], __('messages.credit_card_deleted'));
    }

    /**
     * Registrar un corte: el saldo que dice el banco en la fecha de cierre.
     * Es el ancla del saldo, y se congela en USD al escribirlo.
     */
    public function storeStatement(StoreCreditCardStatementRequest $request, CreditCard $creditCard, StoreStatementAction $action): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $statement = $action->handle($creditCard, $request->validated());

        return $this->apiCreated($statement->id, __('messages.credit_card_statement_created'));
    }

    /**
     * Eliminar un corte de la tarjeta.
     */
    public function destroyStatement(CreditCard $creditCard, CreditCardStatement $statement): JsonResponse
    {
        $this->authorize('update', $creditCard);

        abort_if((int) $statement->credit_card_id !== (int) $creditCard->id, 404);

        $statement->delete();

        return $this->apiResponse(['id' => $statement->id], __('messages.credit_card_statement_deleted'));
    }

    /**
     * Registrar un abono a la tarjeta.
     *
     * No crea ningún gasto: pagar la tarjeta mueve dinero del bolsillo a la
     * deuda, y contarlo también como gasto duplicaría cada consumo.
     */
    public function storePayment(StoreCreditCardPaymentRequest $request, CreditCard $creditCard, StorePaymentAction $action): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $payment = $action->handle($creditCard, $request->validated());

        return $this->apiCreated($payment->id, __('messages.credit_card_payment_created'));
    }

    /**
     * Eliminar un abono de la tarjeta.
     */
    public function destroyPayment(CreditCard $creditCard, CreditCardPayment $payment): JsonResponse
    {
        $this->authorize('update', $creditCard);

        abort_if((int) $payment->credit_card_id !== (int) $creditCard->id, 404);

        $payment->delete();

        return $this->apiResponse(['id' => $payment->id], __('messages.credit_card_payment_deleted'));
    }

    /**
     * Consumos recientes: gastos normales registrados contra el origen de la
     * tarjeta. No hay tabla de movimientos propia.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentCharges(CreditCard $creditCard): array
    {
        if ($creditCard->payment_source_id === null) {
            return [];
        }

        return $creditCard->charges()
            ->with('category')
            ->orderByDesc('spent_at')
            ->orderByDesc('expenses.id')
            ->limit(25)
            ->get()
            ->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'commission' => $expense->commission,
                'currency' => $expense->currency->value,
                'usd_amount' => $expense->usd_amount,
                'spent_at' => $expense->spent_at->toDateString(),
                'category' => $expense->category->name,
            ])
            ->all();
    }
}
