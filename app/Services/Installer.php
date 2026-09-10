<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Shared install logic for the web wizard (`InstallController`) and the CLI
 * (`app:install`). Produces a complete, production-ready `.env` — the operator
 * never has to create or edit one by hand.
 */
class Installer
{
    /**
     * Expected keys (all validated upstream by InstallController / InstallApp):
     * db_connection, db_database, app_name, app_url, app_locale, timezone,
     * admin_name, admin_email, admin_password, plus optional db_host, db_port,
     * db_username, db_password.
     *
     * @param  array<string, string|null>  $data
     */
    public function install(array $data): void
    {
        $connection = (string) $data['db_connection'];

        $this->applyRuntimeDatabaseConfig($connection, $data);
        $this->testDatabaseConnection($data);

        $key = $this->resolveApplicationKey();

        $this->writeEnvFile($data, $key);

        $this->runMigrations($connection);
        $this->createAdminUser($data);
        $this->linkStorage();

        $this->markInstalled();

        // Drop any stale bootstrap cache so the fresh .env takes effect.
        Artisan::call('optimize:clear');
    }

    public function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    public function markInstalled(): void
    {
        file_put_contents(storage_path('installed'), now()->toIso8601String());
    }

    /**
     * @return array{php: array{version: string, status: bool}, extensions: array<string, array{status: bool}>, directories: array<string, array{status: bool}>}
     */
    public function checkRequirements(): array
    {
        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml' => extension_loaded('xml'),
            'curl' => extension_loaded('curl'),
            'zip' => extension_loaded('zip'),
            'bcmath' => extension_loaded('bcmath'),
            'gd' => extension_loaded('gd'),
            'fileinfo' => extension_loaded('fileinfo'),
        ];

        $writableDirs = [
            'raíz de la app (.env)' => is_writable(base_path()),
            'storage/' => is_writable(storage_path()),
            'bootstrap/cache/' => is_writable(app()->bootstrapPath('cache')),
        ];

        return [
            'php' => [
                'version' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.3.0', '>='),
            ],
            'extensions' => array_map(fn (bool $loaded) => ['status' => $loaded], $extensions),
            'directories' => array_map(fn (bool $writable) => ['status' => $writable], $writableDirs),
        ];
    }

    /**
     * @return array{connection: string, host: string, port: string, database: string, username: string, password: string}
     */
    public function defaultDatabase(): array
    {
        return [
            'connection' => config('database.default'),
            'host' => config('database.connections.mysql.host') ?? '127.0.0.1',
            'port' => config('database.connections.mysql.port') ?? '3306',
            'database' => config('database.connections.mysql.database') ?? 'spentz_trackr',
            'username' => config('database.connections.mysql.username') ?? 'root',
            'password' => '',
        ];
    }

    /**
     * Point the given connection (and the default) at the wizard's database for
     * the rest of this request, so migrations and the admin user land there
     * even though `config/database.php` was loaded from the old environment.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyRuntimeDatabaseConfig(string $connection, array $data): void
    {
        config([
            "database.connections.{$connection}" => $this->buildDatabaseConfig($data),
            'database.default' => $connection,
        ]);

        DB::purge($connection);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function testDatabaseConnection(array $data): void
    {
        try {
            if (($data['db_connection'] ?? null) === 'sqlite') {
                $path = database_path((string) ($data['db_database'] ?: 'database.sqlite'));

                if (! file_exists($path)) {
                    @touch($path);
                }
            }

            $config = $this->buildDatabaseConfig($data);
            $config['prefix'] = '';

            $temporaryConnection = '__install_test';

            config(["database.connections.{$temporaryConnection}" => $config]);

            DB::connection($temporaryConnection)->getPdo();

            DB::purge($temporaryConnection);
            config(["database.connections.{$temporaryConnection}" => null]);
        } catch (\Throwable $e) {
            abort(500, 'No se pudo conectar a la base de datos: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildDatabaseConfig(array $data): array
    {
        return match ($data['db_connection']) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => database_path((string) ($data['db_database'] ?: 'database.sqlite')),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => $data['db_host'] ?? '127.0.0.1',
                'port' => $data['db_port'] ?? '3306',
                'database' => $data['db_database'],
                'username' => $data['db_username'] ?? 'root',
                'password' => $data['db_password'] ?? '',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => $data['db_host'] ?? '127.0.0.1',
                'port' => $data['db_port'] ?? '5432',
                'database' => $data['db_database'],
                'username' => $data['db_username'] ?? 'postgres',
                'password' => $data['db_password'] ?? '',
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            default => abort(422, 'Motor de base de datos no soportado.'),
        };
    }

    /**
     * Return a stable APP_KEY: reuse `storage/app.key` when present (this is how
     * the key survives container recreation on Docker), otherwise generate one
     * and persist it there. Also applies it to the running config.
     */
    private function resolveApplicationKey(): string
    {
        $keyFile = storage_path('app.key');
        $current = (string) config('app.key');

        $key = $current !== ''
            ? $current
            : (is_file($keyFile) ? trim((string) file_get_contents($keyFile)) : '');

        if ($key === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
        }

        @file_put_contents($keyFile, $key);
        config()->set('app.key', $key);

        return $key;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeEnvFile(array $data, string $appKey): void
    {
        $envPath = base_path('.env');

        $env = is_file($envPath) ? (string) file_get_contents($envPath) : '';

        if ($env === '' && is_file(base_path('.env.example'))) {
            $env = (string) file_get_contents(base_path('.env.example'));
        }

        if (is_file($envPath)) {
            @copy($envPath, base_path('.env.backup'));
        }

        $url = rtrim((string) $data['app_url'], '/');
        $secureCookie = str_starts_with($url, 'https://') ? 'true' : 'false';

        $values = [
            'APP_NAME' => (string) $data['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'APP_LOCALE' => (string) $data['app_locale'],
            'APP_TIMEZONE' => (string) ($data['timezone'] ?? 'UTC'),
            'APP_INSTALL_MODE' => $this->currentValue($env, 'APP_INSTALL_MODE') ?: 'wizard',
            'DB_CONNECTION' => (string) $data['db_connection'],
            'DB_HOST' => (string) ($data['db_host'] ?? ''),
            'DB_PORT' => (string) ($data['db_port'] ?? ''),
            'DB_DATABASE' => (string) $data['db_database'],
            'DB_USERNAME' => (string) ($data['db_username'] ?? ''),
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => $secureCookie,
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
        ];

        foreach ($values as $key => $value) {
            $env = $this->setEnvValue($env, $key, $value);
        }

        if (file_put_contents($envPath, rtrim($env, "\n")."\n") === false) {
            abort(500, 'No se pudo escribir el archivo .env. Dale permisos de escritura a la carpeta de la aplicación.');
        }
    }

    private function currentValue(string $env, string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=("?)(.*)\1\s*$/m', $env, $m) === 1) {
            return trim($m[2]);
        }

        return null;
    }

    private function setEnvValue(string $env, string $key, string $value): string
    {
        $line = $key.'='.$this->envValue($value);

        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $env) === 1) {
            return (string) preg_replace_callback($pattern, fn (): string => $line, $env, 1);
        }

        return rtrim($env, "\n")."\n".$line."\n";
    }

    private function envValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return Str::contains($value, [' ', '#', '"', '='])
            ? '"'.addcslashes($value, '"\\').'"'
            : $value;
    }

    private function runMigrations(string $connection): void
    {
        Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
        ]);
    }

    /**
     * Best-effort public/storage symlink for expense/income receipts. Some
     * shared hosts disallow symlink(); the operator can run it manually or
     * point the disk elsewhere.
     */
    private function linkStorage(): void
    {
        try {
            if (! is_link(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        } catch (\Throwable) {
            // Ignore — receipts just won't be web-served until storage:link runs.
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAdminUser(array $data): void
    {
        User::query()->forceCreate([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make((string) $data['admin_password']),
            'email_verified_at' => now(),
            'is_admin' => true,
            'tracking_type' => 'both',
            'locale' => $data['app_locale'],
        ]);
    }
}
