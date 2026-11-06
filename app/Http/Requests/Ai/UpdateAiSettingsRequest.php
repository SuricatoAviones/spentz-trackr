<?php

namespace App\Http\Requests\Ai;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            // Identificadores de modelo reales: "gpt-4.1-mini",
            // "meta-llama/llama-4-scout-17b-16e-instruct", "qwen2.5vl:7b".
            'model' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/@+\-]+$/'],
            'api_key' => ['nullable', 'string', 'min:8', 'max:500', 'regex:/^\S+$/'],
            'base_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $provider = AiProvider::from((string) $this->input('provider'));

            if (! $provider->isAvailable()) {
                $validator->errors()->add('provider', __('validation.app.ai_provider_unavailable'));

                return;
            }

            if ($provider->requiresBaseUrl() && trim((string) $this->input('base_url')) === '') {
                $validator->errors()->add('base_url', __('validation.app.ai_base_url_required'));
            }

            $options = $provider->endpointOptions();
            $baseUrl = $this->input('base_url');

            if ($options !== [] && $baseUrl !== null && $baseUrl !== '' && ! in_array($baseUrl, $options, true)) {
                $validator->errors()->add('base_url', __('validation.app.ai_endpoint_option'));
            }

            if ($provider === AiProvider::Custom && trim((string) $this->input('model')) === '') {
                $validator->errors()->add('model', __('validation.app.ai_model_required'));
            }

            if ($provider->requiresApiKey() && trim((string) $this->input('api_key')) === '' && ! $this->keepsStoredKey($provider)) {
                $validator->errors()->add('api_key', __('validation.app.ai_key_required'));
            }
        });
    }

    /**
     * Sin clave nueva solo vale la guardada, y solo si es del mismo proveedor.
     */
    private function keepsStoredKey(AiProvider $provider): bool
    {
        $setting = $this->user()?->aiSetting;

        return $setting instanceof AiSetting
            && $setting->provider === $provider
            && $setting->hasApiKey();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model.regex' => __('validation.app.ai_model_format'),
            'api_key.regex' => __('validation.app.ai_key_format'),
            'base_url.url' => __('validation.app.ai_base_url_format'),
        ];
    }
}
