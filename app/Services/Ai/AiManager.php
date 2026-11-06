<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use App\Models\User;
use App\Services\Ai\Drivers\AiDriver;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Support\Features;

/**
 * Punto de entrada a la IA: resuelve el driver del proveedor que el usuario
 * conectó. Toda petición pasa por aquí, así que aquí se comprueban el
 * interruptor de instancia y que la configuración esté completa.
 */
class AiManager
{
    public function forUser(User $user): AiDriver
    {
        if (! Features::aiEnabled()) {
            throw AiException::because(AiException::DISABLED);
        }

        $setting = $user->aiSetting;

        if (! $setting instanceof AiSetting || ! $setting->isReady()) {
            throw AiException::because(AiException::NOT_CONFIGURED);
        }

        return $this->driverFor($setting);
    }

    public function driverFor(AiSetting $setting): AiDriver
    {
        return match ($setting->provider->driver()) {
            'anthropic' => new AnthropicDriver($setting),
            'gemini' => new GeminiDriver($setting),
            default => new OpenAiDriver($setting),
        };
    }
}
