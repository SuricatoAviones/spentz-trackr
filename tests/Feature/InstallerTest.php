<?php

use App\Console\Commands\InstallApp;
use App\Console\Commands\UpdateApp;
use App\Services\Installer;

beforeEach(function () {
    $this->installedPath = storage_path('installed');

    if (file_exists($this->installedPath)) {
        unlink($this->installedPath);
    }
});

afterEach(function () {
    if (file_exists($this->installedPath)) {
        unlink($this->installedPath);
    }
});

test('the installer reports all requirements as installed', function () {
    $installer = app(Installer::class);

    $requirements = $installer->checkRequirements();

    expect($requirements['php']['status'])->toBeTrue()
        ->and($requirements['php']['version'])->toBe(PHP_VERSION);

    collect($requirements['extensions'])->each(
        fn (array $requirement) => expect($requirement['status'])->toBeTrue(),
    );

    collect($requirements['directories'])->each(
        fn (array $requirement) => expect($requirement['status'])->toBeTrue(),
    );
});

test('the default database returns the mysql shape by default', function () {
    $default = app(Installer::class)->defaultDatabase();

    expect($default)->toBeArray()
        ->toHaveKeys(['connection', 'host', 'port', 'database', 'username', 'password']);
});

test('the app:install command is registered in artisan', function () {
    expect(array_key_exists('app:install', Artisan::all()))->toBeTrue();
});

test('the app:update command is registered in artisan', function () {
    expect(array_key_exists('app:update', Artisan::all()))->toBeTrue();
});

test('the install command classes expose the expected signature', function () {
    expect((new ReflectionClass(InstallApp::class))->isInstantiable())->toBeTrue()
        ->and((new ReflectionClass(UpdateApp::class))->isInstantiable())->toBeTrue();
});

test('the requirements endpoint is reachable while the app is not installed', function () {
    $this->get('/install')
        ->assertStatus(200);
});

test('the database step returns a json response for the wizard', function () {
    $this->postJson('/install/database', [
        'connection' => 'sqlite',
        'database' => 'database/database.sqlite',
    ])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('the execute endpoint returns a json response when validation fails', function () {
    $this->postJson('/install/execute', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['db_connection', 'app_name', 'admin_email']);
});

test('install routes are blocked once the app is already installed', function () {
    file_put_contents($this->installedPath, now()->toIso8601String());

    $this->get('/install')->assertForbidden();
    $this->postJson('/install/execute', [])->assertForbidden();
});
