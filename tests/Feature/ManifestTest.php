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
        ->assertJsonPath('icons.0.src', '/images/logo.png')
        ->assertJsonCount(4, 'icons');
});
