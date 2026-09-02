<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('the ajustes page exposes the commission rule defaults', function () {
    $this->get(route('ajustes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ajustes')
            ->where('commissionDefaults.min_commission', '14.00')
            ->where('commissionDefaults.commission_rate', '0.30')
        );
});

test('a user can save the commission rule', function () {
    $this->put(route('commission-preferences.update'), [
        'min_commission' => 20,
        'commission_rate' => 0.5,
    ])->assertRedirect();

    expect($this->user->fresh()->min_commission)->toBe('20.00')
        ->and($this->user->fresh()->commission_rate)->toBe('0.50');
});

test('a blank commission rule field is saved as null', function () {
    $this->put(route('commission-preferences.update'), [
        'min_commission' => '',
        'commission_rate' => '',
    ])->assertRedirect();

    expect($this->user->fresh()->min_commission)->toBeNull()
        ->and($this->user->fresh()->commission_rate)->toBeNull();
});

test('a negative minimum commission is rejected', function () {
    $this->put(route('commission-preferences.update'), [
        'min_commission' => -5,
        'commission_rate' => 0.3,
    ])->assertSessionHasErrors('min_commission');

    expect($this->user->fresh()->min_commission)->toBe('14.00');
});

test('a commission rate over 100 is rejected', function () {
    $this->put(route('commission-preferences.update'), [
        'min_commission' => 14,
        'commission_rate' => 150,
    ])->assertSessionHasErrors('commission_rate');

    expect($this->user->fresh()->commission_rate)->toBe('0.30');
});
