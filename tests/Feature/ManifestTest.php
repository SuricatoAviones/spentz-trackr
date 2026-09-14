<?php

use App\Models\User;

test('the manifest is public and served with the manifest content type', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json');
});

test('the manifest follows the resolved locale', function () {
    $spanish = User::factory()->create(['locale' => 'es']);

    $this->actingAs($spanish)
        ->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('lang', 'es')
        ->assertJsonPath('description', __('messages.pwa_description', locale: 'es'));

    $english = User::factory()->create(['locale' => 'en']);

    $this->actingAs($english)
        ->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('lang', 'en')
        ->assertJsonPath('description', __('messages.pwa_description', locale: 'en'));
});

test('the manifest keeps the brand name untranslated and the PWA fields intact', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', config('app.name'))
        ->assertJsonPath('short_name', config('app.name'))
        ->assertJsonPath('start_url', '/')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.src', '/icons/icon-192.png')
        ->assertJsonCount(4, 'icons');
});

test('maskable icons are their own files, not the wide lockup', function () {
    // Android recorta los `maskable` al 80 % central. Apuntarlos al lockup
    // horizontal le cortaba el texto, así que llevan arte propio a sangre.
    $icons = $this->get('/manifest.webmanifest')->json('icons');

    $maskable = array_values(array_filter(
        $icons,
        fn (array $icon): bool => $icon['purpose'] === 'maskable',
    ));

    expect($maskable)->toHaveCount(2);

    foreach ($maskable as $icon) {
        expect($icon['src'])->toStartWith('/icons/maskable-')
            ->and($icon['src'])->not->toBe('/images/logo.png');
    }
});

test('every icon the manifest advertises actually exists', function () {
    // Un icono declarado y ausente rompe la instalación del PWA en silencio.
    foreach ($this->get('/manifest.webmanifest')->json('icons') as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeReadableFile();
    }
});
