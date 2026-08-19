<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Database\Seeders\DefaultCategoriesSeeder;
use Database\Seeders\DefaultPaymentSourcesSeeder;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        foreach (DefaultCategoriesSeeder::DEFAULT_CATEGORIES as $category) {
            $user->categories()->create([...$category, 'is_system' => true]);
        }

        foreach (DefaultPaymentSourcesSeeder::DEFAULT_SOURCES as $source) {
            $user->paymentSources()->create([...$source, 'is_system' => true]);
        }

        return $user;
    }
}
