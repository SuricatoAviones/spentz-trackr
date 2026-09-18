<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SavingsGoals\DeleteContributionAction;
use App\Actions\SavingsGoals\StoreContributionAction;
use App\Actions\SavingsGoals\StoreSavingsGoalAction;
use App\Actions\SavingsGoals\UpdateSavingsGoalAction;
use App\Http\Requests\StoreSavingsContributionRequest;
use App\Http\Requests\StoreSavingsGoalRequest;
use App\Http\Requests\UpdateSavingsGoalRequest;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Support\Presenters\SavingsGoalPresenter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavingsGoalController extends BaseApiController
{
    /**
     * Listar las metas de ahorro del usuario con lo aportado y su progreso.
     */
    #[QueryParameter('achieved', description: 'Filtrar por estado: 1 solo las cumplidas, 0 solo las pendientes.', type: 'boolean')]
    public function index(Request $request): JsonResponse
    {
        $goals = SavingsGoal::query()
            ->forUser($request->user()->id)
            ->with('contributions')
            ->withSum('contributions as saved_usd', 'usd_amount')
            ->when($request->has('achieved'), function ($query) use ($request) {
                $request->boolean('achieved')
                    ? $query->whereNotNull('achieved_at')
                    : $query->whereNull('achieved_at');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SavingsGoal $goal) => SavingsGoalPresenter::present($goal))
            ->all();

        return $this->apiResponse([
            'goals' => $goals,
        ]);
    }

    /**
     * Crear una meta de ahorro. El objetivo se congela en USD al guardarlo.
     */
    public function store(StoreSavingsGoalRequest $request, StoreSavingsGoalAction $action): JsonResponse
    {
        $goal = $action->handle($request->user(), $request->validated());

        return $this->apiCreated($goal->id, __('messages.savings_goal_created'))
            ->header('Location', route('api.v1.savings-goals.show', $goal));
    }

    /**
     * Ver una meta de ahorro con sus aportes.
     */
    public function show(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('view', $savingsGoal);

        return $this->apiResponse([
            'goal' => SavingsGoalPresenter::present($savingsGoal->load('contributions')),
        ]);
    }

    /**
     * Actualizar una meta de ahorro.
     */
    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal, UpdateSavingsGoalAction $action): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $action->handle($savingsGoal, $request->validated());

        return $this->apiResponse([
            'goal' => SavingsGoalPresenter::present($savingsGoal->fresh(['contributions'])),
        ], __('messages.savings_goal_updated'));
    }

    /**
     * Eliminar una meta de ahorro y sus aportes.
     */
    public function destroy(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('delete', $savingsGoal);

        $savingsGoal->delete();

        return $this->apiResponse(['id' => $savingsGoal->id], __('messages.savings_goal_deleted'));
    }

    /**
     * Registrar un aporte a la meta. Se congela en USD con la tasa enviada.
     */
    public function storeContribution(StoreSavingsContributionRequest $request, SavingsGoal $savingsGoal, StoreContributionAction $action): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $contribution = $action->handle($savingsGoal, $request->validated());

        return $this->apiCreated($contribution->id, __('messages.savings_contribution_added'));
    }

    /**
     * Eliminar un aporte de la meta.
     */
    public function destroyContribution(SavingsGoal $savingsGoal, SavingsContribution $contribution, DeleteContributionAction $action): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $action->handle($savingsGoal, $contribution);

        return $this->apiResponse(['id' => $contribution->id], __('messages.savings_contribution_deleted'));
    }
}
