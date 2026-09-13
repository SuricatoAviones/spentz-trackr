<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corta el acceso vivo de una cuenta.
 *
 * Cambiar la contraseña no servía de nada frente a un atacante ya dentro: la
 * sesión que tuviera abierta y los tokens de API que hubiera emitido seguían
 * siendo válidos. Se revocan ambos.
 *
 * `Auth::logoutOtherDevices()` no vale aquí porque depende del middleware
 * `AuthenticateSession`, que esta app no monta; se borran las filas de sesión
 * directamente, que además funciona para el reset que hace un admin sobre otro
 * usuario (donde no hay sesión del afectado a mano).
 */
final class AccountAccess
{
    /**
     * Revoca todas las sesiones y tokens del usuario.
     *
     * @param  string|null  $exceptSessionId  Sesión a conservar (la del propio usuario que acaba de cambiar su clave).
     */
    public static function revoke(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();

        self::forgetSessions($user, $exceptSessionId);
    }

    private static function forgetSessions(User $user, ?string $exceptSessionId): void
    {
        // Solo el driver `database` guarda las sesiones donde podamos buscarlas
        // por user_id; con `file`/`redis` la tabla existe pero está vacía y el
        // borrado no hace nada. No se filtra por driver a propósito: así el
        // camino se ejercita en los tests (que corren con driver `array`) y la
        // revocación de tokens cubre el resto.
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
