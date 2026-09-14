<?php

namespace App\Policies;

use App\Models\CreditCard;
use App\Models\User;

class CreditCardPolicy
{
    public function view(User $user, CreditCard $card): bool
    {
        return $card->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreditCard $card): bool
    {
        return $card->user_id === $user->id;
    }

    public function delete(User $user, CreditCard $card): bool
    {
        return $card->user_id === $user->id;
    }
}
