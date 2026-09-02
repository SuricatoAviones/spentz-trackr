<?php

use App\Models\Category;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the app default locale (Spanish) is used when no preference exists', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

test('the user locale preference wins over the app default', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
        ]),
    ]);

    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('the session locale is used when the user has no preference', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('authenticated users can persist their language preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('language.update'), ['locale' => 'en'])
        ->assertRedirect();

    expect($user->fresh()->locale)->toBe('en');
    expect(session('locale'))->toBe('en');
});

test('an invalid locale is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('language.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

test('guests are redirected to login when changing the language', function () {
    $this->post(route('language.update'), ['locale' => 'en'])
        ->assertRedirect(route('login'));
});

test('validation messages are rendered in Spanish by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), [])
        ->assertSessionHasErrors(['category_id', 'payment_source_id', 'currency', 'amount', 'description', 'spent_at'])
        ->assertSessionHasErrors(['description' => 'El campo descripción es obligatorio.']);
});

test('flash messages follow the session locale', function () {
    Http::fake([
        've.dolarapi.com/*' => Http::response([
            ['moneda' => 'USD', 'fuente' => 'oficial', 'promedio' => 31.25],
        ]),
    ]);

    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $source = PaymentSource::factory()->for($user)->create();

    $payload = [
        'category_id' => $category->id,
        'payment_source_id' => $source->id,
        'currency' => 'usd',
        'amount' => 10,
        'description' => 'Coffee',
        'spent_at' => today()->toDateString(),
    ];

    $this->actingAs($user)
        ->post(route('expenses.store'), $payload)
        ->assertSessionHas('success', 'Gasto registrado correctamente.');

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->post(route('expenses.store'), $payload)
        ->assertSessionHas('success', 'Expense recorded successfully.');
});
