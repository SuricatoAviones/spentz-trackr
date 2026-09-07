<?php

namespace App\Console\Commands;

use App\Services\Installer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature(
    'app:install
    {--db-connection= : Conexión a usar (sqlite, mysql o pgsql).}
    {--db-host= : Host de la base de datos.}
    {--db-port= : Puerto de la base de datos.}
    {--db-database= : Nombre de la base de datos.}
    {--db-username= : Usuario de la base de datos.}
    {--db-password= : Contraseña de la base de datos.}
    {--app-name= : Nombre de la aplicación.}
    {--app-url= : URL pública de la aplicación.}
    {--app-locale= : Idioma por defecto (es o en).}
    {--timezone= : Zona horaria por defecto.}
    {--admin-name= : Nombre del administrador.}
    {--admin-email= : Correo del administrador.}
    {--admin-password= : Contraseña del administrador.}
    {--force : Ignorar que ya existe una instalación y sobrescribir.}'
)]
#[Description('Instala Spentz Trackr por línea de comandos (base de datos, .env y administrador).')]
class InstallApp extends Command
{
    public function handle(Installer $installer): int
    {
        if ($installer->isInstalled() && ! $this->option('force')) {
            $this->error('La aplicación ya está instalada. Usa --force para sobrescribir.');

            return self::FAILURE;
        }

        $this->line('Revisando requisitos...');

        $requirements = $installer->checkRequirements();

        if (! $requirements['php']['status']) {
            $this->error('Se requiere PHP 8.3 o superior.');

            return self::FAILURE;
        }

        $missing = collect($requirements['extensions'])
            ->reject(fn (array $req) => $req['status'])
            ->keys();

        if ($missing->isNotEmpty()) {
            $this->error('Faltan extensiones PHP: '.$missing->implode(', '));

            return self::FAILURE;
        }

        $data = $this->collectData($this->input->isInteractive());

        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            if (! $this->input->isInteractive() && $validator->errors()->has('admin_password')) {
                $length = mb_strlen((string) ($data['admin_password'] ?? ''));
                $this->warn("ADMIN_PASSWORD recibida por entorno con {$length} caracteres. Si el valor contiene '#' u otros caracteres especiales, el proveedor pudo truncarlo; cítalo o evítalos.");
            }

            return self::FAILURE;
        }

        if ($data['db_connection'] === 'sqlite') {
            touch(database_path($data['db_database']));
        }

        $this->line('Escribiendo configuración, generando clave y migrando...');

        try {
            $installer->install($validator->validated());
        } catch (\Throwable $e) {
            $this->error('Error durante la instalación: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('¡Instalación completada!');
        $this->info('Admin: '.$data['admin_email']);
        $this->warn('Cambia la contraseña del administrador tras el primer inicio de sesión.');

        return self::SUCCESS;
    }

    /**
     * Reúne la configuración de instalación: prioridad flag CLI → variable de
     * entorno → prompt interactivo (o null en modo no interactivo/headless).
     *
     * @return array<string, mixed>
     */
    private function collectData(bool $interactive): array
    {
        $data = [
            'db_connection' => $this->optionOrEnv('db-connection', 'DB_CONNECTION') ?? ($interactive ? $this->chooseConnection() : null),
            'db_host' => $this->optionOrEnv('db-host', 'DB_HOST'),
            'db_port' => $this->optionOrEnv('db-port', 'DB_PORT'),
            'db_database' => $this->optionOrEnv('db-database', 'DB_DATABASE'),
            'db_username' => $this->optionOrEnv('db-username', 'DB_USERNAME'),
            'db_password' => $this->optionOrEnv('db-password', 'DB_PASSWORD'),
            'app_name' => $this->optionOrEnv('app-name', 'APP_NAME') ?? ($interactive ? $this->ask('Nombre de la aplicación', config('app.name', 'Spentz Trackr')) : config('app.name', 'Spentz Trackr')),
            'app_url' => $this->optionOrEnv('app-url', 'APP_URL') ?? ($interactive ? $this->ask('URL pública de la aplicación', config('app.url')) : config('app.url')),
            'app_locale' => $this->optionOrEnv('app-locale', 'APP_LOCALE') ?? ($interactive ? $this->choice('Idioma por defecto', ['es', 'en'], 0) : config('app.locale', 'es')),
            'timezone' => $this->optionOrEnv('timezone', 'TIMEZONE') ?? ($interactive ? $this->ask('Zona horaria por defecto', config('app.timezone', 'UTC')) : config('app.timezone', 'UTC')),
            'admin_name' => $this->optionOrEnv('admin-name', 'ADMIN_NAME') ?? ($interactive ? $this->ask('Nombre del administrador') : null),
            'admin_email' => $this->optionOrEnv('admin-email', 'ADMIN_EMAIL') ?? ($interactive ? $this->ask('Correo del administrador') : null),
            'admin_password' => $this->optionOrEnv('admin-password', 'ADMIN_PASSWORD') ?? ($interactive ? $this->secret('Contraseña del administrador (mín. 8 caracteres)') : null),
        ];

        if ($data['db_connection'] !== 'sqlite') {
            $data['db_host'] = $data['db_host'] ?? ($interactive ? $this->ask('Host de la base de datos', '127.0.0.1') : '127.0.0.1');
            $data['db_port'] = $data['db_port'] ?? ($interactive ? $this->ask('Puerto', $this->defaultPort($data['db_connection'])) : $this->defaultPort($data['db_connection']));
            $data['db_database'] = $data['db_database'] ?? ($interactive ? $this->ask('Nombre de la base de datos') : null);
            $data['db_username'] = $data['db_username'] ?? ($interactive ? $this->ask('Usuario', $data['db_connection'] === 'pgsql' ? 'postgres' : 'root') : ($data['db_connection'] === 'pgsql' ? 'postgres' : 'root'));
            $data['db_password'] = $data['db_password'] ?? ($interactive ? $this->secret('Contraseña de la base de datos') : null);
        } else {
            $data['db_database'] = $data['db_database'] ?? 'database.sqlite';
        }

        return $data;
    }

    private function optionOrEnv(string $option, string $env): ?string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $envValue = env($env);

        return is_string($envValue) && $envValue !== '' ? $envValue : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'db_connection' => ['required', 'in:sqlite,mysql,pgsql'],
            'db_host' => ['nullable', 'string'],
            'db_port' => ['nullable', 'string'],
            'db_database' => ['required', 'string'],
            'db_username' => ['nullable', 'string'],
            'db_password' => ['nullable', 'string'],
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_locale' => ['required', 'in:es,en'],
            'timezone' => ['required', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }

    private function chooseConnection(): string
    {
        $choice = $this->choice('Conexión de base de datos', ['sqlite', 'mysql', 'pgsql'], 0);

        return (string) $choice;
    }

    private function defaultPort(string $connection): string
    {
        return $connection === 'pgsql' ? '5432' : '3306';
    }
}
