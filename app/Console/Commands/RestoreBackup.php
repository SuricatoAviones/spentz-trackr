<?php

namespace App\Console\Commands;

use App\Support\BackupSchema;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Restaura una copia generada desde el panel (Sistema → Backup).
 *
 * Una copia que no se sabe restaurar es una falsa sensación de seguridad, así
 * que este comando es la otra mitad del backup y comparte con él `BackupSchema`.
 *
 * Dos límites que se avisan en voz alta, no se esconden:
 *
 *  - El fichero NO lleva hashes de contraseña (ver BackupSchema::secretColumns),
 *    así que los usuarios restaurados no pueden entrar hasta recuperar su clave.
 *    Es el precio de que un backup descargable no sea también un volcado de
 *    credenciales.
 *  - Tampoco lleva los ficheros de comprobantes, solo sus filas. Para una
 *    recuperación byte a byte hace falta un volcado de la base de datos y una
 *    copia de `storage/app/private`.
 */
class RestoreBackup extends Command
{
    use ConfirmableTrait;

    protected $signature = 'backup:restore
                            {file : Ruta al fichero .json generado por el panel}
                            {--fresh : Vacía las tablas antes de restaurar}
                            {--force : Ejecutar en producción sin preguntar}';

    protected $description = 'Restaura una copia de seguridad en formato JSON';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("No existe el fichero: {$path}");

            return self::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('El fichero no es JSON válido: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($payload) || ! array_key_exists('users', $payload)) {
            $this->error('El fichero no parece una copia de Spentz Trackr.');

            return self::FAILURE;
        }

        $version = $payload['format_version'] ?? 0;

        if ($version > BackupSchema::VERSION) {
            $this->error("La copia usa el formato v{$version} y esta versión entiende hasta la v".BackupSchema::VERSION.'.');
            $this->line('Actualiza la aplicación antes de restaurarla.');

            return self::FAILURE;
        }

        $tables = BackupSchema::tables();

        if (! $this->option('fresh') && $this->hasExistingData($tables)) {
            $this->error('Ya hay datos en la base. Restaurar encima provocaría choques de id.');
            $this->line('Usa --fresh para vaciar las tablas primero (borra lo que haya).');

            return self::FAILURE;
        }

        $counts = [];

        try {
            DB::transaction(function () use ($payload, $tables, &$counts): void {
                if ($this->option('fresh')) {
                    // Al revés que al insertar: primero los hijos.
                    foreach (array_reverse($tables) as $table) {
                        DB::table($table)->delete();
                    }
                }

                foreach ($tables as $key => $table) {
                    $rows = $payload[$key] ?? [];

                    if (! is_array($rows) || $rows === []) {
                        $counts[$table] = 0;

                        continue;
                    }

                    if ($table === 'users') {
                        $rows = array_map($this->withPlaceholderPassword(...), $rows);
                    }

                    // En trozos: una copia con años de gastos no cabe en una
                    // sola sentencia.
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    $counts[$table] = count($rows);
                }
            });
        } catch (Throwable $e) {
            // La transacción ya deshizo lo aplicado: la base queda como estaba.
            $this->error('La restauración falló y se deshizo entera: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Copia restaurada.');
        $this->newLine();

        $this->table(
            ['Tabla', 'Filas'],
            collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all(),
        );

        $this->newLine();
        $this->warn('Las contraseñas NO viajan en la copia: cada usuario debe recuperar la suya.');
        $this->line('Para el administrador puedes usar: php artisan admin:create');
        $this->line('Los comprobantes tampoco: restaura aparte storage/app/private si los guardaste.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function hasExistingData(array $tables): bool
    {
        foreach ($tables as $table) {
            if (DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Contraseña aleatoria para un usuario restaurado.
     *
     * Nadie la conoce —tampoco quien restaura— y no queda utilizable: la cuenta
     * existe con sus datos, pero solo entra tras recuperar la contraseña. Dejar
     * el campo nulo reventaría la columna, y poner una conocida abriría todas
     * las cuentas a la vez.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withPlaceholderPassword(array $row): array
    {
        $row['password'] = Hash::make(Str::random(64));

        return $row;
    }
}
