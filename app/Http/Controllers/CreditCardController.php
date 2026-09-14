<?php

namespace App\Http\Controllers;

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
use App\Services\CreditCardCycleService;
use App\Services\ExchangeRateService;
use App\Support\Presenters\CreditCardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Request $request, CreditCardCycleService $cycle, ExchangeRateService $rateService): Response
    {
        $user = $request->user();

        $rateService->ensureFreshRate($user);

        $cards = CreditCard::query()
            ->forUser($user->id)
            ->with('paymentSource')
            ->orderByDesc('active')
            ->orderBy('bank')
            ->get()
            ->map(fn (CreditCard $card) => CreditCardPresenter::present($card, $cycle))
            ->all();

        return Inertia::render('credit-cards/index', [
            'cards' => $cards,
            'rate' => $rateService->rateForUser($user),
        ]);
    }

    public function show(Request $request, CreditCard $creditCard, CreditCardCycleService $cycle, ExchangeRateService $rateService): Response
    {
        $this->authorize('view', $creditCard);

        $user = $request->user();
        $rateService->ensureFreshRate($user);

        $creditCard->load(['statements', 'payments', 'paymentSource']);

        return Inertia::render('credit-cards/show', [
            'card' => CreditCardPresenter::detail($creditCard, $cycle),
            'rate' => $rateService->rateForUser($user),
            'recentCharges' => $this->recentCharges($creditCard),
        ]);
    }

    public function store(StoreCreditCardRequest $request, StoreCreditCardAction $action): RedirectResponse
    {
        $card = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('credit-cards.show', $card)
            ->with('success', __('messages.credit_card_created'));
    }

    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard, UpdateCreditCardAction $action): RedirectResponse
    {
        $this->authorize('update', $creditCard);

        $action->handle($creditCard, $request->validated());

        return back()->with('success', __('messages.credit_card_updated'));
    }

    public function destroy(Request $request, CreditCard $creditCard, DeleteCreditCardAction $action): RedirectResponse
    {
        $this->authorize('delete', $creditCard);

        $action->handle($creditCard);

        return redirect()
            ->route('credit-cards.index')
            ->with('success', __('messages.credit_card_deleted'));
    }

    public function storeStatement(StoreCreditCardStatementRequest $request, CreditCard $creditCard, StoreStatementAction $action): RedirectResponse
    {
        $this->authorize('update', $creditCard);

        $action->handle($creditCard, $request->validated());

        return back()->with('success', __('messages.credit_card_statement_created'));
    }

    public function destroyStatement(Request $request, CreditCard $creditCard, CreditCardStatement $statement): RedirectResponse
    {
        $this->authorize('update', $creditCard);

        abort_if((int) $statement->credit_card_id !== (int) $creditCard->id, 404);

        $statement->delete();

        return back()->with('success', __('messages.credit_card_statement_deleted'));
    }

    public function storePayment(StoreCreditCardPaymentRequest $request, CreditCard $creditCard, StorePaymentAction $action): RedirectResponse
    {
        $this->authorize('update', $creditCard);

        $action->handle($creditCard, $request->validated());

        return back()->with('success', __('messages.credit_card_payment_created'));
    }

    public function destroyPayment(Request $request, CreditCard $creditCard, CreditCardPayment $payment): RedirectResponse
    {
        $this->authorize('update', $creditCard);

        abort_if((int) $payment->credit_card_id !== (int) $creditCard->id, 404);

        $payment->delete();

        return back()->with('success', __('messages.credit_card_payment_deleted'));
    }

    /**
     * Consumos del ciclo actual: gastos normales registrados contra el origen
     * de la tarjeta. No hay tabla de movimientos propia.
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
            ->map(fn ($expense) => [
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
