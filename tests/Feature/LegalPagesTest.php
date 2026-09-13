<?php

use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Inertia\Testing\AssertableInertia as Assert;

test('the legal pages are reachable without an account', function () {
    // Alguien tiene que poder leerlos ANTES de aceptar nada.
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/document')
            ->where('document', 'terms')
            ->has('content.title')
            ->has('content.sections')
        );

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/document')
            ->where('document', 'privacy')
            ->has('content.sections')
        );
});

test('operator placeholders are replaced, never shown raw', function () {
    config([
        'legal.operator' => 'Cooperativa Ejemplo',
        'legal.contact_email' => 'datos@ejemplo.test',
        'legal.jurisdiction' => 'Venezuela',
    ]);

    foreach (['legal.terms', 'legal.privacy'] as $route) {
        $content = $this->get(route($route))->viewData('page')['props']['content'];

        $flat = json_encode($content, JSON_UNESCAPED_UNICODE);

        expect($flat)->not->toContain(':operator')
            ->and($flat)->not->toContain(':email')
            ->and($flat)->not->toContain(':jurisdiction')
            ->and($flat)->not->toContain(':date')
            ->and($flat)->toContain('Cooperativa Ejemplo');
    }
});

test('the documents are served in the reader locale', function () {
    $english = User::factory()->create(['locale' => 'en', 'email_verified_at' => now()]);

    $spanish = $this->get(route('legal.privacy'))->viewData('page')['props']['content'];
    $translated = $this->actingAs($english)->get(route('legal.privacy'))->viewData('page')['props']['content'];

    expect($spanish['title'])->toBe('Política de datos')
        ->and($translated['title'])->toBe('Data policy');
});

test('es and en legal files keep identical key sets', function () {
    // Si una sección existe solo en un idioma, el lector del otro se queda sin
    // una cláusula sin que nada avise.
    $flatten = function (array $tree, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($tree as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value)
                ? array_merge($keys, $flatten($value, $full))
                : array_merge($keys, [$full]);
        }

        return $keys;
    };

    $es = $flatten(Lang::get('legal', [], 'es'));
    $en = $flatten(Lang::get('legal', [], 'en'));

    expect(array_diff($es, $en))->toBe([])
        ->and(array_diff($en, $es))->toBe([]);
});
