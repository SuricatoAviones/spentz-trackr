<?php

namespace App\Actions\SavingsGoals;

use App\Actions\SavingsGoals\Concerns\RefreshesAchievement;
use App\Models\SavingsGoal;

class UpdateSavingsGoalAction
{
    use RefreshesAchievement;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SavingsGoal $goal, array $data): SavingsGoal
    {
        $goal->update($this->freezeTarget($data));

        // Subir el objetivo puede "desconseguir" una meta ya lograda.
        $this->refreshAchievement($goal);

        return $goal;
    }
}
