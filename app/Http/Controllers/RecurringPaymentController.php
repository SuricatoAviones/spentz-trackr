<?php

namespace App\Http\Controllers;

use App\Actions\RecurringPayments\MarkRecurringPaymentPaidAction;
use App\Actions\RecurringPayments\StoreRecurringPaymentAction;
use App\Actions\RecurringPayments\UpdateRecurringPaymentAction;
use App\Enums\CategoryType;
use App\Http\Requests\StoreRecurringPaymentRequest;
use App\Http\Requests\UpdateRecurringPaymentRequest;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Support\Presenters\RecurringPaymentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $payments = RecurringPayment::query()
            ->forUser($user->id)
            ->get()
            ->map(fn (RecurringPayment $payment) => RecurringPaymentPresenter::present($payment))
            ->sortBy([
                fn (array $a, array $b) => (int) $b['due'] <=> (int) $a['due'],
                fn (array $a, array $b) => strcmp($a['next_due_date'] ?? '', $b['next_due_date'] ?? ''),
            ])
            ->values()
            ->all();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->forType(CategoryType::Expense)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ])
            ->all();

        return Inertia::render('recurring-payments/index', [
            'payments' => $payments,
            'categories' => $categories,
        ]);
    }

    public function store(StoreRecurringPaymentRequest $request, StoreRecurringPaymentAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_created'));
    }

    public function update(UpdateRecurringPaymentRequest $request, RecurringPayment $recurringPayment, UpdateRecurringPaymentAction $action): RedirectResponse
    {
        $this->authorize('update', $recurringPayment);

        $action->handle($recurringPayment, $request->validated());

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_updated'));
    }

    public function destroy(Request $request, RecurringPayment $recurringPayment): RedirectResponse
    {
        $this->authorize('delete', $recurringPayment);

        $recurringPayment->delete();

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_deleted'));
    }

    public function markPaid(Request $request, RecurringPayment $recurringPayment, MarkRecurringPaymentPaidAction $action): RedirectResponse
    {
        $this->authorize('update', $recurringPayment);

        $action->handle($recurringPayment);

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_paid'));
    }
}
