<?php

namespace App\Http\Controllers;

use App\Enums\TrackingType;
use App\Http\Requests\UpdateCommissionDefaultsRequest;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AjustesController extends Controller
{
    public function __invoke(Request $request, ExchangeRateService $rateService): Response
    {
        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('ajustes', [
            'rate' => $rateService->rateForUser($user),
            'commissionDefaults' => [
                'min_commission' => $user->min_commission,
                'commission_rate' => $user->commission_rate,
            ],
            'trackingType' => $user->tracking_type,
            'monthlyBudget' => $user->monthly_budget,
            'monthlySpent' => round((float) $user->expenses()
                ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
                ->sum('usd_amount'), 2),
            'monthlyExpenseCount' => $user->expenses()
                ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
                ->count(),
            'monthlyIncomeCount' => $user->incomes()
                ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
                ->count(),
        ]);
    }

    public function updateMonthlyBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $request->user()->update([
            'monthly_budget' => $validated['monthly_budget'] !== null && $validated['monthly_budget'] !== ''
                ? $validated['monthly_budget']
                : null,
        ]);

        return back()->with('success', __('messages.budget_updated'));
    }

    public function updateCommissions(UpdateCommissionDefaultsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // The commission form always submits both fields; a blank value clears
        // the default (falls back to the form's own zero handling).
        $request->user()->update([
            'min_commission' => $validated['min_commission'] ?? null,
            'commission_rate' => $validated['commission_rate'] ?? null,
        ]);

        return back()->with('success', __('messages.commissions_updated'));
    }

    public function updateTracking(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_type' => ['required', Rule::enum(TrackingType::class)],
        ]);

        $request->user()->update([
            'tracking_type' => $validated['tracking_type'],
        ]);

        return back()->with('success', __('messages.tracking_updated'));
    }
}
