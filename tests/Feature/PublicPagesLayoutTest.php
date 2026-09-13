<?php

/*
 * Regresión de "una página pública nueva sale en blanco".
 *
 * El resolvedor de layouts de `resources/js/app.tsx` manda al `default` —
 * TrackerLayout— todo lo que no tenga una rama propia. TrackerLayout lee
 * `auth.user.tracking_type`, así que con un visitante anónimo (`auth.user` es
 * null) lanza un TypeError y React no pinta nada: pantalla en blanco, respuesta
 * 200 y ni un error en los logs del servidor. Pasó al añadir /terminos.
 *
 * Ninguna prueba de Inertia renderiza React, así que el fallo no se ve desde
 * PHP. Lo que sí se puede comprobar es el contrato: toda página alcanzable sin
 * sesión debe tener una rama explícita en el switch.
 */

use Illuminate\Support\Facades\Route;

/**
 * Componentes Inertia que devuelven las rutas GET sin middleware `auth`.
 *
 * @return list<string>
 */
function publicInertiaComponents(): array
{
    $components = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        $needsAuth = collect($middleware)->contains(
            fn ($name) => is_string($name) && (str_starts_with($name, 'auth') || $name === 'admin'),
        );

        if ($needsAuth) {
            continue;
        }

        // `Route::inertia()` guarda el componente en los defaults de la ruta.
        $component = $route->defaults['component'] ?? null;

        if (is_string($component)) {
            $components[] = $component;
        }
    }

    return array_values(array_unique($components));
}

test('every public page has its own branch in the layout resolver', function () {
    // Las que renderiza un controlador no salen de la tabla de rutas, así que
    // se nombran aquí. Al añadir una página pública nueva, añadirla también.
    $controllerRendered = ['legal/document'];

    $components = array_merge(publicInertiaComponents(), $controllerRendered);

    expect($components)->not->toBeEmpty();

    $appTsx = file_get_contents(resource_path('js/app.tsx'));

    foreach ($components as $component) {
        // Vale una rama exacta (`name === 'welcome'`) o por prefijo
        // (`name.startsWith('legal/')`).
        $prefix = str_contains($component, '/')
            ? explode('/', $component)[0].'/'
            : $component;

        $hasBranch = str_contains($appTsx, "name === '{$component}'")
            || str_contains($appTsx, "name.startsWith('{$prefix}')");

        expect($hasBranch)->toBeTrue(
            "La página pública '{$component}' cae en el `default` del switch de app.tsx, "
            .'o sea TrackerLayout, que revienta sin usuario autenticado.',
        );
    }
});

test('the public legal pages really render with no authenticated user', function () {
    // Comprobación de humo del lado servidor: 200 y `auth.user` nulo, que es
    // exactamente la combinación que hacía estallar al layout equivocado.
    foreach (['legal.terms', 'legal.privacy'] as $name) {
        $props = $this->get(route($name))->assertOk()->viewData('page')['props'];

        expect($props['auth']['user'])->toBeNull();
    }
});
