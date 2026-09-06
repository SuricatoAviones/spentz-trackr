<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature(
    'app:update
    {--force : Ejecutar aunque la aplicación esté en modo producción.}'
)]
#[Description('Actualiza Spentz Trackr desde git (pull, dependencias, migraciones y cachés).')]
class UpdateApp extends Command
{
    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('La aplicación está en producción. Usa --force para actualizar de todas formas.');

            return self::FAILURE;
        }

        $steps = [
            'Actualizando desde git' => 'git pull --ff-only',
            'Instalando dependencias de PHP' => 'composer install --no-dev --no-interaction --prefer-dist',
            'Instalando dependencias de JS' => 'npm ci --no-audit --no-fund',
            'Compilando assets' => 'npm run build',
            'Ejecutando migraciones' => PHP_BINARY.' artisan migrate --force',
            'Limpiando cachés' => PHP_BINARY.' artisan optimize:clear',
        ];

        $total = count($steps);
        $index = 0;

        foreach ($steps as $label => $command) {
            $index++;

            $this->line("[{$index}/{$total}] {$label}");

            $process = $this->runShell($command);

            if (! $process->isSuccessful()) {
                $this->error($process->getErrorOutput() ?: $process->getOutput());
                $this->error('La actualización falló en: '.$label);

                return self::FAILURE;
            }

            $this->info($process->getOutput());
        }

        $this->info('¡Actualización completada!');

        return self::SUCCESS;
    }

    private function runShell(string $command): Process
    {
        return Process::fromShellCommandline($command, base_path())
            ->setTimeout(null)
            ->setIdleTimeout(null);
    }
}
