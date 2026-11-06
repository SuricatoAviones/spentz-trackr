<?php

namespace App\Actions\Ai;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\User;

/**
 * Guarda el proveedor de IA de un usuario.
 *
 * Una clave vacía significa "conserva la que hay", para que cambiar el modelo
 * no obligue a pegar la clave otra vez. Cambiar de proveedor, en cambio, la
 * descarta: una clave de OpenAI no sirve en Anthropic, y conservarla haría que
 * la primera petición fallara con un 401 incomprensible.
 */
class UpdateAiSettingsAction
{
    /**
     * @param  array<string, mixed>  $data  validado por UpdateAiSettingsRequest
     */
    public function handle(User $user, array $data): AiSetting
    {
        $provider = AiProvider::from($data['provider']);
        $setting = $user->aiSetting ?? new AiSetting(['provider' => $provider]);
        $providerChanged = $setting->exists && $setting->provider !== $provider;

        $newKey = trim((string) ($data['api_key'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $baseUrl = $provider->requiresBaseUrl() ? rtrim(trim((string) ($data['base_url'] ?? '')), '/') : null;

        $setting->provider = $provider;
        $setting->model = $model !== '' ? $model : null;
        $setting->base_url = $baseUrl !== '' ? $baseUrl : null;

        if ($newKey !== '') {
            $setting->api_key = $newKey;
        } elseif ($providerChanged) {
            $setting->api_key = null;
        }

        // Cualquier cambio invalida la última verificación.
        if ($setting->isDirty()) {
            $setting->last_verified_at = null;
        }

        $setting->user()->associate($user);
        $setting->save();

        $user->setRelation('aiSetting', $setting);

        return $setting;
    }
}
