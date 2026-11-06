<?php

namespace App\Support\Presenters;

use App\Enums\AiProvider;
use App\Models\AiSetting;

/**
 * Forma pública de la configuración de IA. **Nunca incluye la clave**: solo si
 * hay una guardada y sus últimos caracteres, para que el usuario la reconozca.
 */
final class AiSettingPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function present(?AiSetting $setting): ?array
    {
        if (! $setting instanceof AiSetting) {
            return null;
        }

        return [
            'provider' => $setting->provider->value,
            'provider_label' => $setting->provider->label(),
            'model' => $setting->model,
            'resolved_model' => $setting->resolvedModel(),
            'base_url' => $setting->provider->requiresBaseUrl() ? $setting->base_url : null,
            'has_key' => $setting->hasApiKey(),
            'masked_key' => $setting->maskedKey(),
            'ready' => $setting->isReady(),
            'supports_vision' => $setting->provider->supportsVision(),
            'supports_pdf' => $setting->provider->supportsPdf(),
            'last_verified_at' => $setting->last_verified_at?->toIso8601String(),
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Catálogo de proveedores para el selector.
     *
     * @return list<array<string, mixed>>
     */
    public static function providers(): array
    {
        return array_map(fn (AiProvider $provider): array => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'default_model' => $provider->defaultModel(),
            'models' => $provider->suggestedModels(),
            'default_base_url' => $provider->requiresBaseUrl() ? $provider->defaultBaseUrl() : null,
            'requires_key' => $provider->requiresApiKey(),
            'requires_base_url' => $provider->requiresBaseUrl(),
            'supports_vision' => $provider->supportsVision(),
            'supports_pdf' => $provider->supportsPdf(),
            'key_url' => $provider->apiKeyUrl(),
            'available' => $provider->isAvailable(),
        ], AiProvider::cases());
    }

    /**
     * Lo que necesita el formulario de gasto para ofrecer "leer factura".
     *
     * @return array{available: bool, pdf: bool}
     */
    public static function scanCapabilities(?AiSetting $setting): array
    {
        $ready = $setting instanceof AiSetting && $setting->isReady() && $setting->provider->supportsVision();

        return [
            'available' => $ready,
            'pdf' => $ready && $setting->provider->supportsPdf(),
        ];
    }
}
