<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSetting>
 */
class AiSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4.1-mini',
            'api_key' => 'sk-test-'.fake()->regexify('[A-Za-z0-9]{32}'),
            'base_url' => null,
        ];
    }

    public function provider(AiProvider $provider, ?string $model = null): static
    {
        return $this->state([
            'provider' => $provider,
            'model' => $model ?? $provider->defaultModel(),
            'api_key' => $provider->requiresApiKey() ? 'key-'.fake()->regexify('[A-Za-z0-9]{32}') : null,
            'base_url' => $provider->requiresBaseUrl() ? 'http://ollama.test:11434/v1' : null,
        ]);
    }
}
