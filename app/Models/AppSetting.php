<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de instancia editables desde el panel admin.
 *
 * Se leen en cada petición (el interruptor de la API vive aquí), así que el mapa
 * completo va a caché y se invalida al escribir. Son cuatro filas: cargarlas
 * todas de golpe sale más barato que una consulta por clave.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    private const CACHE_KEY = 'app-settings';

    /**
     * Valor de un ajuste, con el default de `config/features.php` detrás.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::map()[$key] ?? $default;
    }

    public static function boolean(string $key, bool $default): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        self::query()->updateOrCreate(['key' => $key], ['value' => $stored]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    private static function map(): array
    {
        /*
         * La tabla puede no existir todavía: `migrate` arranca la app para
         * correrse a sí mismo, y cualquier lectura anterior a esta migración
         * reventaría el despliegue. Sin tabla mandan los defaults, y NO se
         * cachea: si se guardara el vacío para siempre, los ajustes reales no
         * se verían nunca después de migrar.
         */
        if (! Schema::hasTable('app_settings')) {
            return [];
        }

        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('value', 'key')->all(),
        );
    }
}
