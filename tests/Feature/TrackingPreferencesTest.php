<?php

use App\Enums\TrackingType;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create(['tracking_type' => 'both']);
    $this->actingAs($this->user);
});

test('guests are redirected to the login page on the tracking preferences endpoint', function () {
    auth()->logout();

    $this->put(route('tracking-preferences.update'), [
        'tracking_type' => TrackingType::Both->value,
    ])->assertRedirect(route('login'));
});

test('a user can update their tracking preference', function () {
    $this->put(route('tracking-preferences.update'), [
        'tracking_type' => TrackingType::Expenses->value,
    ])->assertRedirect();

    expect($this->user->refresh()->tracking_type)->toBe('expenses');
});

test('an invalid tracking preference is rejected', function () {
    $this->put(route('tracking-preferences.update'), [
        'tracking_type' => 'unknown',
    ])->assertSessionHasErrors('tracking_type');

    expect($this->user->refresh()->tracking_type)->toBe('both');
});

test('the ajustes page exposes the tracking preference and both counts', function () {
    $this->get(route('ajustes'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ajustes')
            ->where('trackingType', 'both')
            ->where('monthlyExpenseCount', 0)
            ->where('monthlyIncomeCount', 0)
        );
});
