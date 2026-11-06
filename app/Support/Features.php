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

    public const REGISTRATION = 'registration_enabled';

    public const AI = 'ai_enabled';

    public static function apiEnabled(): bool
    {
        return AppSetting::boolean(self::API, (bool) config('features.api', true));
    }

    public static function setApiEnabled(bool $enabled): void
    {
        AppSetting::set(self::API, $enabled);
    }

    public static function registrationEnabled(): bool
    {
        return AppSetting::boolean(self::REGISTRATION, (bool) config('features.registration', true));
    }

    public static function setRegistrationEnabled(bool $enabled): void
    {
        AppSetting::set(self::REGISTRATION, $enabled);
    }

    /**
     * Asistente de IA (lectura de facturas, chat, análisis). Apagarlo no borra
     * las claves guardadas: al volver a encenderlo cada usuario sigue
     * conectado a su proveedor.
     */
    public static function aiEnabled(): bool
    {
        return AppSetting::boolean(self::AI, (bool) config('features.ai', true));
    }

    public static function setAiEnabled(bool $enabled): void
    {
        AppSetting::set(self::AI, $enabled);
    }

    /**
     * Estado de los interruptores tal y como lo consume el frontend.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return [
            'api' => self::apiEnabled(),
            'registration' => self::registrationEnabled(),
            'ai' => self::aiEnabled(),
        ];
    }
}
