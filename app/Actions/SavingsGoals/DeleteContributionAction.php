<?php

namespace App\Actions\SavingsGoals;

use App\Actions\SavingsGoals\Concerns\RefreshesAchievement;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;

class DeleteContributionAction
{
    use RefreshesAchievement;

    /**
     * El aporte tiene que ser de ESTA meta: sin comprobarlo, el id de la ruta
     * permitiría borrar el aporte de otra meta (o de otro usuario).
     */
    public function handle(SavingsGoal $goal, SavingsContribution $contribution): void
    {
        abort_if((int) $contribution->savings_goal_id !== (int) $goal->id, 404);

        $contribution->delete();

        $this->refreshAchievement($goal);
    }
}
