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

        $data = [
            'db_connection' => $this->option('db-connection') ?? $this->chooseConnection(),
            'db_host' => $this->option('db-host'),
            'db_port' => $this->option('db-port'),
            'db_database' => $this->option('db-database'),
            'db_username' => $this->option('db-username'),
            'db_password' => $this->option('db-password'),
            'app_name' => $this->option('app-name') ?? $this->ask('Nombre de la aplicación', config('app.name', 'Spentz Trackr')),
            'app_url' => $this->option('app-url') ?? $this->ask('URL pública de la aplicación', config('app.url')),
            'app_locale' => $this->option('app-locale') ?? $this->choice('Idioma por defecto', ['es', 'en'], 0),
            'timezone' => $this->option('timezone') ?? $this->ask('Zona horaria por defecto', config('app.timezone', 'UTC')),
            'admin_name' => $this->option('admin-name') ?? $this->ask('Nombre del administrador'),
            'admin_email' => $this->option('admin-email') ?? $this->ask('Correo del administrador'),
            'admin_password' => $this->option('admin-password') ?? $this->secret('Contraseña del administrador (mín. 8 caracteres)'),
        ];

        if ($data['db_connection'] !== 'sqlite') {
            $data['db_host'] = $data['db_host'] ?? $this->ask('Host de la base de datos', '127.0.0.1');
            $data['db_port'] = $data['db_port'] ?? $this->ask('Puerto', $this->defaultPort($data['db_connection']));
            $data['db_database'] = $data['db_database'] ?? $this->ask('Nombre de la base de datos');
            $data['db_username'] = $data['db_username'] ?? $this->ask('Usuario', $data['db_connection'] === 'pgsql' ? 'postgres' : 'root');
            $data['db_password'] = $data['db_password'] ?? $this->secret('Contraseña de la base de datos');
        } else {
            $data['db_database'] = $data['db_database'] ?? 'database.sqlite';
        }

        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
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
