<?php

use App\Console\Commands\InstallApp;
use App\Console\Commands\UpdateApp;
use App\Services\Installer;

/**
 * Pone variables de entorno en las tres fuentes que lee env() (getenv,
 * $_ENV y $_SERVER) y devuelve sus valores previos para restaurarlas.
 *
 * @param  array<string, string>  $values
 * @return array<string, string|false>
 */
function setInstallEnvForTest(array $values): array
{
    $previous = [];

    foreach ($values as $key => $value) {
        $previous[$key] = getenv($key);

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    return $previous;
}

/**
 * @param  array<string, string|false>  $previous
 */
function restoreInstallEnvForTest(array $previous): void
{
    foreach ($previous as $key => $value) {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * @return array{php: array{version: string, status: bool}, extensions: array<string, array{status: bool}>}
 */
function installerRequirementsForTest(): array
{
    return [
        'php' => ['version' => PHP_VERSION, 'status' => true],
        'extensions' => collect([
            'pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'curl', 'zip', 'bcmath', 'gd', 'fileinfo',
        ])->mapWithKeys(fn (string $extension) => [$extension => ['status' => true]])->all(),
    ];
}

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

test('install routes are blocked when APP_INSTALL_MODE is headless', function () {
    $previous = setInstallEnvForTest(['APP_INSTALL_MODE' => 'headless']);

    try {
        $this->get('/install')->assertNotFound();
        $this->postJson('/install/execute', [])->assertNotFound();
    } finally {
        restoreInstallEnvForTest($previous);
    }
});

test('app:install reads database and admin settings from the environment in non-interactive mode', function () {
    $installer = Mockery::mock(Installer::class);
    $installer->shouldReceive('isInstalled')->once()->andReturn(false);
    $installer->shouldReceive('checkRequirements')->once()->andReturn(installerRequirementsForTest());
    $installer->shouldReceive('install')->once()->with(Mockery::on(function (array $validated): bool {
        expect($validated['db_connection'])->toBe('sqlite')
            ->and($validated['db_database'])->toBe('install-env-test.sqlite')
            ->and($validated['app_name'])->toBe('Spentz Env')
            ->and($validated['app_url'])->toBe('https://spentz.example.test')
            ->and($validated['app_locale'])->toBe('es')
            ->and($validated['timezone'])->toBe('America/Caracas')
            ->and($validated['admin_name'])->toBe('Admin Env')
            ->and($validated['admin_email'])->toBe('admin@env.test')
            ->and($validated['admin_password'])->toBe('secret-env');

        return true;
    }));

    $this->app->instance(Installer::class, $installer);

    $previous = setInstallEnvForTest([
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => 'install-env-test.sqlite',
        'APP_NAME' => 'Spentz Env',
        'APP_URL' => 'https://spentz.example.test',
        'APP_LOCALE' => 'es',
        'TIMEZONE' => 'America/Caracas',
        'ADMIN_NAME' => 'Admin Env',
        'ADMIN_EMAIL' => 'admin@env.test',
        'ADMIN_PASSWORD' => 'secret-env',
    ]);

    try {
        $this->artisan('app:install', ['--no-interaction' => true])->assertSuccessful();

        expect(file_exists(database_path('install-env-test.sqlite')))->toBeTrue();
    } finally {
        restoreInstallEnvForTest($previous);

        @unlink(database_path('install-env-test.sqlite'));
    }
});

test('app:install fails gracefully when admin settings are incomplete in non-interactive mode', function () {
    $installer = Mockery::mock(Installer::class);
    $installer->shouldReceive('isInstalled')->once()->andReturn(false);
    $installer->shouldReceive('checkRequirements')->once()->andReturn(installerRequirementsForTest());
    $installer->shouldReceive('install')->never();

    $this->app->instance(Installer::class, $installer);

    $previous = setInstallEnvForTest([
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => 'database.sqlite',
        'ADMIN_NAME' => 'Admin Env',
        'ADMIN_EMAIL' => 'admin@env.test',
        'ADMIN_PASSWORD' => 'x',
    ]);

    try {
        $this->artisan('app:install', ['--no-interaction' => true])
            ->assertFailed()
            ->expectsOutput('El campo admin password debe tener al menos 8 caracteres.')
            ->expectsOutputToContain('ADMIN_PASSWORD recibida por entorno con 1 caracteres.');
    } finally {
        restoreInstallEnvForTest($previous);
    }
});
