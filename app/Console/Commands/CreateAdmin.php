<?php

namespace App\Console\Commands;

use App\Actions\Users\AssignDefaultUserDataAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('admin:create')]
#[Description('Crea o actualiza el usuario administrador con las credenciales de .env')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AssignDefaultUserDataAction $defaults): int
    {
        $name = (string) config('admin.name');
        $email = (string) config('admin.email');
        $password = (string) config('admin.password');

        if ($password === '') {
            $password = Str::password(16);

            $this->warn('ADMIN_PASSWORD no está definido en .env; se generó una contraseña aleatoria.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $created = ! $user->exists;

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
        ])->save();

        // El admin no pasa por Fortify\CreateNewUser, así que las categorías y
        // orígenes por defecto se asignan aquí. La acción es idempotente: no
        // toca al admin que ya los tiene.
        $defaults->handle($user);

        if ($created) {
            $this->info("Administrador creado: {$email}");
        } else {
            $this->info("Administrador actualizado: {$email}");
        }

        $this->info("Email: {$email}");
        $this->info("Contraseña: {$password}");
        $this->warn('Cambia la contraseña tras el primer inicio de sesión.');

        return self::SUCCESS;
    }
}
