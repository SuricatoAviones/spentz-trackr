<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Punto único para consultar los interruptores de instancia.
 *
 * Orden de precedencia: lo que haya guardado un admin desde el panel gana; si
 * nadie lo ha tocado, manda el default de `config/features.php` (que lee .env).
 */
final class Features
{
    public const API = 'api_enabled';

    public static function apiEnabled(): bool
    {
        return AppSetting::boolean(self::API, (bool) config('features.api', true));
    }

    public static function setApiEnabled(bool $enabled): void
    {
        AppSetting::set(self::API, $enabled);
    }
}
