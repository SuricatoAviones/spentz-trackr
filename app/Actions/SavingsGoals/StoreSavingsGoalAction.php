<?php

namespace App\Actions\SavingsGoals;

use App\Actions\SavingsGoals\Concerns\RefreshesAchievement;
use App\Models\SavingsGoal;
use App\Models\User;

/**
 * Crea una meta de ahorro. Compartida por la web y la API: si cambia el
 * congelado del objetivo, se toca aquí una sola vez.
 */
class StoreSavingsGoalAction
{
    use RefreshesAchievement;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): SavingsGoal
    {
        return $user->savingsGoals()->create($this->freezeTarget($data));
    }
}
