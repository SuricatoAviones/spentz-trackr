<?php

namespace App\Http\Controllers;

use App\Actions\SavingsGoals\DeleteContributionAction;
use App\Actions\SavingsGoals\StoreContributionAction;
use App\Actions\SavingsGoals\StoreSavingsGoalAction;
use App\Actions\SavingsGoals\UpdateSavingsGoalAction;
use App\Http\Requests\StoreSavingsContributionRequest;
use App\Http\Requests\StoreSavingsGoalRequest;
use App\Http\Requests\UpdateSavingsGoalRequest;
use App\Models\Income;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Support\Presenters\SavingsGoalPresenter;
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
            ->map(fn (SavingsGoal $goal) => SavingsGoalPresenter::present($goal));

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

    public function store(StoreSavingsGoalRequest $request, StoreSavingsGoalAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_goal_created'));
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal, UpdateSavingsGoalAction $action): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $action->handle($savingsGoal, $request->validated());

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

    public function storeContribution(StoreSavingsContributionRequest $request, SavingsGoal $goal, StoreContributionAction $action): RedirectResponse
    {
        $this->authorize('update', $goal);

        $action->handle($goal, $request->validated());

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_contribution_added'));
    }

    public function destroyContribution(Request $request, SavingsGoal $goal, SavingsContribution $contribution, DeleteContributionAction $action): RedirectResponse
    {
        $this->authorize('update', $goal);

        $action->handle($goal, $contribution);

        return redirect()
            ->route('savings-goals.index')
            ->with('success', __('messages.savings_contribution_deleted'));
    }
}
