<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Http\Requests\StoreRecurringPaymentRequest;
use App\Http\Requests\UpdateRecurringPaymentRequest;
use App\Models\Category;
use App\Models\RecurringPayment;
use App\Services\ExpenseConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->map(fn (RecurringPayment $payment) => $this->shape($payment))
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

    public function store(StoreRecurringPaymentRequest $request, ExpenseConversionService $converter): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);

        $converted = $converter->convert(
            $currency,
            (float) $validated['amount'],
            $currency === Currency::Ves ? (float) $validated['exchange_rate'] : null,
        );

        $user->recurringPayments()->create([
            ...$validated,
            'exchange_rate' => $currency === Currency::Ves ? $validated['exchange_rate'] : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
            'last_paid_at' => null,
        ]);

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_created'));
    }

    public function update(UpdateRecurringPaymentRequest $request, RecurringPayment $recurringPayment, ExpenseConversionService $converter): RedirectResponse
    {
        $this->authorize('update', $recurringPayment);

        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);

        $converted = $converter->convert(
            $currency,
            (float) $validated['amount'],
            $currency === Currency::Ves ? (float) $validated['exchange_rate'] : null,
        );

        $recurringPayment->update([
            ...$validated,
            'exchange_rate' => $currency === Currency::Ves ? $validated['exchange_rate'] : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

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

    public function markPaid(Request $request, RecurringPayment $recurringPayment): RedirectResponse
    {
        $this->authorize('update', $recurringPayment);

        $nextDue = $this->nextDueDate($recurringPayment, now()->toDateString());

        $recurringPayment->update([
            'last_paid_at' => now()->toDateString(),
            'next_due_date' => $nextDue->format('Y-m-d'),
        ]);

        return redirect()
            ->route('recurring-payments.index')
            ->with('success', __('messages.recurring_payment_paid'));
    }

    private function nextDueDate(RecurringPayment $payment, string $fromDate): \DateTimeInterface
    {
        return $payment->frequency->advance(Carbon::parse($fromDate));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(RecurringPayment $payment): array
    {
        $due = $payment->active
            && $payment->next_due_date !== null
            && ! $payment->next_due_date->isAfter(today());

        return [
            'id' => $payment->id,
            'name' => $payment->name,
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'usd_amount' => $payment->usd_amount,
            'frequency' => $payment->frequency->value,
            'frequency_label' => $payment->frequency->label(),
            'next_due_date' => $payment->next_due_date?->toDateString(),
            'last_paid_at' => $payment->last_paid_at?->toDateString(),
            'category_id' => $payment->category_id,
            'icon' => $payment->icon,
            'color' => $payment->color,
            'active' => $payment->active,
            'note' => $payment->note,
            'due' => $due,
        ];
    }
}
