<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        $this->testDatabaseConnection($data);

        $this->writeEnvFile($data);

        $this->generateAppKey();

        $this->runMigrations((string) $data['db_connection']);

        $this->createAdminUser($data);

        $this->markInstalled();
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
            storage_path() => is_writable(storage_path()),
            app()->bootstrapPath('cache') => is_writable(app()->bootstrapPath('cache')),
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
            'database' => config('database.connections.mysql.database') ?? 'spent_trackr',
            'username' => config('database.connections.mysql.username') ?? 'root',
            'password' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function testDatabaseConnection(array $data): void
    {
        try {
            $config = $this->buildDatabaseConfig($data);

            $config['prefix'] = '';

            $temporaryConnection = '__install_test';

            config(["database.connections.{$temporaryConnection}" => $config]);

            $connection = DB::connection($temporaryConnection);

            $connection->getPdo();

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
                'database' => database_path($data['db_database'] ?: 'database.sqlite'),
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
     * @param  array<string, mixed>  $data
     */
    private function writeEnvFile(array $data): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $env = file_get_contents($envPath);

        if ($env === false) {
            abort(500, 'No se pudo leer el archivo .env.');
        }

        $replacements = [
            'APP_NAME='.$this->envValue((string) $data['app_name']),
            'APP_URL='.$this->envValue(rtrim((string) $data['app_url'], '/')),
            'APP_LOCALE='.$this->envValue((string) $data['app_locale']),
            'DB_CONNECTION='.$this->envValue((string) $data['db_connection']),
            'DB_HOST='.$this->envValue((string) ($data['db_host'] ?? '')),
            'DB_PORT='.$this->envValue((string) ($data['db_port'] ?? '')),
            'DB_DATABASE='.$this->envValue((string) $data['db_database']),
            'DB_USERNAME='.$this->envValue((string) ($data['db_username'] ?? '')),
            'DB_PASSWORD='.$this->envValue((string) ($data['db_password'] ?? '')),
            'SESSION_DRIVER=database',
            'QUEUE_CONNECTION=database',
            'CACHE_STORE=database',
        ];

        foreach ($replacements as $line) {
            $key = explode('=', $line, 2)[0];

            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $env)) {
                $env = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $env);
            } else {
                $env .= "\n".$line;
            }
        }

        file_put_contents($envPath, $env);
    }

    private function envValue(string $value): string
    {
        return str_contains($value, ' ') || str_contains($value, '#')
            ? '"'.addslashes($value).'"'
            : $value;
    }

    private function generateAppKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    private function runMigrations(string $connection): void
    {
        Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
        ]);
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
