<?php

use App\Actions\Users\AssignDefaultUserDataAction;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new users get the default categories and payment sources', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->categories()->count())->toBe(count(AssignDefaultUserDataAction::DEFAULT_CATEGORIES))
        ->and($user->paymentSources()->count())->toBe(count(AssignDefaultUserDataAction::DEFAULT_PAYMENT_SOURCES))
        ->and($user->categories()->pluck('name'))->toContain('Alimentación')
        ->and($user->paymentSources()->pluck('name'))->toContain('Pago Móvil')
        ->and($user->categories()->where('is_system', true)->count())->toBe(count(AssignDefaultUserDataAction::DEFAULT_CATEGORIES));
});
