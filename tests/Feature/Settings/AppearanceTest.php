<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('appearance page is displayed with the language preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/appearance')
            ->where('locale', 'es'),
        );
});

test('appearance page reflects the user language preference', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/appearance')
            ->where('locale', 'en'),
        );
});
