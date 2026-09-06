<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Requests\StoreSavingsContributionRequest;
use App\Http\Requests\StoreSavingsGoalRequest;
use App\Http\Requests\UpdateSavingsGoalRequest;
use App\Models\Income;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Services\ExpenseConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsGoalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $goals = SavingsGoal::query()
            ->forUser($user->id)
            ->with('contributions')
            ->withSum('contributions as saved_usd', 'usd_amount')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SavingsGoal $goal) => $this->shape($goal));

        $incomes = Income::query()
            ->forUser($user->id)
            ->orderByDesc('received_at')
            ->limit(200)
            ->get(['id', 'description', 'received_at', 'usd_amount'])
            ->map(fn (Income $income) => [
                'id' => $income->id,
                'description' => $income->description,
                'received_at' => $income->received_at->toDateString(),
                'usd_amount' => $income->usd_amount,
            ])
            ->all();

        return Inertia::render('savings-goals/index', [
            'goals' => $goals,
            'incomes' => $incomes,
        ]);
    }

    public function store(StoreSavingsGoalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);

        $validated['exchange_rate'] = $currency === Currency::Ves ? $validated['exchange_rate'] : null;
        $validated['target_usd_amount'] = $currency === Currency::Ves
            ? round((float) $validated['target_amount'] / (float) $validated['exchange_rate'], 2)
            : round((float) $validated['target_amount'], 2);

        $request->user()->savingsGoals()->create($validated);

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_goal_created'));
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);

        $validated['exchange_rate'] = $currency === Currency::Ves ? $validated['exchange_rate'] : null;
        $validated['target_usd_amount'] = $currency === Currency::Ves
            ? round((float) $validated['target_amount'] / (float) $validated['exchange_rate'], 2)
            : round((float) $validated['target_amount'], 2);

        $savingsGoal->update($validated);
        $this->refreshAchievement($savingsGoal);

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_goal_updated'));
    }

    public function destroy(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('delete', $savingsGoal);

        $savingsGoal->delete();

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_goal_deleted'));
    }

    public function storeContribution(StoreSavingsContributionRequest $request, SavingsGoal $goal, ExpenseConversionService $converter): RedirectResponse
    {
        $this->authorize('update', $goal);

        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);
        $exchangeRate = $validated['exchange_rate'] ?? null;

        $converted = $converter->convert(
            $currency,
            (float) $validated['amount'],
            $currency === Currency::Ves ? (float) $exchangeRate : null,
        );

        $goal->contributions()->create([
            ...$validated,
            'exchange_rate' => $currency === Currency::Ves ? $exchangeRate : null,
            'usd_amount' => $converted['usd_amount'],
            'usdt_amount' => $converted['usdt_amount'],
        ]);

        $this->refreshAchievement($goal);

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_contribution_added'));
    }

    public function destroyContribution(Request $request, SavingsGoal $goal, SavingsContribution $contribution): RedirectResponse
    {
        $this->authorize('update', $goal);

        if ((int) $contribution->savings_goal_id !== (int) $goal->id) {
            abort(404);
        }

        $contribution->delete();
        $this->refreshAchievement($goal);

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_contribution_deleted'));
    }

    private function refreshAchievement(SavingsGoal $goal): void
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
     * @return array<string, mixed>
     */
    private function shape(SavingsGoal $goal): array
    {
        $saved = round((float) $goal->saved_usd, 2);
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
                ->map(fn (SavingsContribution $contribution) => [
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
}
