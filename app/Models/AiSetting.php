<?php

namespace App\Models;

use App\Enums\AiProvider;
use Database\Factories\AiSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Proveedor de IA de un usuario, con su clave cifrada.
 *
 * La clave nunca sale hacia el cliente: `api_key` está en `Hidden` y el
 * presenter solo publica `has_key` y los cuatro últimos caracteres.
 *
 * @property int $id
 * @property int $user_id
 * @property AiProvider $provider
 * @property string|null $model
 * @property string|null $api_key
 * @property string|null $base_url
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['provider', 'model', 'api_key', 'base_url', 'last_verified_at'])]
#[Hidden(['api_key'])]
class AiSetting extends Model
{
    /** @use HasFactory<AiSettingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedModel(): string
    {
        $model = trim((string) $this->model);

        return $model !== '' ? $model : (string) $this->provider->defaultModel();
    }

    public function resolvedBaseUrl(): string
    {
        $url = $this->provider->requiresBaseUrl()
            ? (string) ($this->base_url ?: $this->provider->defaultBaseUrl())
            : (string) $this->provider->defaultBaseUrl();

        return rtrim($url, '/');
    }

    public function hasApiKey(): bool
    {
        return trim((string) $this->api_key) !== '';
    }

    /**
     * Si con lo guardado se puede hacer una petición: clave cuando el
     * proveedor la exige, modelo y un proveedor que el operador permita.
     */
    public function isReady(): bool
    {
        if (! $this->provider->isAvailable()) {
            return false;
        }

        if ($this->provider->requiresApiKey() && ! $this->hasApiKey()) {
            return false;
        }

        return $this->resolvedModel() !== '' && $this->resolvedBaseUrl() !== '';
    }

    /**
     * "••••abcd": lo justo para que el usuario reconozca qué clave guardó.
     */
    public function maskedKey(): ?string
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $key = (string) $this->api_key;

        return '••••'.(mb_strlen($key) > 8 ? mb_substr($key, -4) : '');
    }

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }
}
