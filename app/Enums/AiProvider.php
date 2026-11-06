<?php

namespace App\Enums;

/**
 * Proveedores de IA que un usuario puede conectar con su propia clave.
 *
 * Solo hay tres protocolos de transporte (`driver()`): OpenAI Chat
 * Completions —que hablan casi todos—, Anthropic Messages y Google Gemini.
 * Añadir un proveedor compatible con OpenAI es añadir un caso aquí, sin tocar
 * los drivers.
 *
 * Los modelos sugeridos son solo eso: el usuario puede escribir cualquier otro
 * identificador, porque los catálogos cambian más rápido que esta app.
 */
enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Mistral = 'mistral';
    case Groq = 'groq';
    case DeepSeek = 'deepseek';
    case OpenRouter = 'openrouter';
    case OpenCode = 'opencode';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic (Claude)',
            self::Gemini => 'Google Gemini',
            self::Mistral => 'Mistral AI',
            self::Groq => 'Groq',
            self::DeepSeek => 'DeepSeek',
            self::OpenRouter => 'OpenRouter',
            self::OpenCode => 'OpenCode',
            self::Ollama => 'Ollama',
            self::Custom => 'OpenAI-compatible',
        };
    }

    /**
     * Protocolo de transporte: `openai`, `anthropic` o `gemini`.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Anthropic => 'anthropic',
            self::Gemini => 'gemini',
            default => 'openai',
        };
    }

    public function defaultBaseUrl(): ?string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1',
            self::Anthropic => 'https://api.anthropic.com/v1',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            self::Mistral => 'https://api.mistral.ai/v1',
            self::Groq => 'https://api.groq.com/openai/v1',
            self::DeepSeek => 'https://api.deepseek.com/v1',
            self::OpenRouter => 'https://openrouter.ai/api/v1',
            self::OpenCode => 'https://opencode.ai/inference/openai/v1',
            self::Ollama => 'http://localhost:11434/v1',
            self::Custom => null,
        };
    }

    public function defaultModel(): ?string
    {
        return $this->suggestedModels()[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function suggestedModels(): array
    {
        return match ($this) {
            self::OpenAi => ['gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5', 'gpt-4o-mini'],
            self::Anthropic => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
            self::Gemini => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite'],
            self::Mistral => ['mistral-medium-latest', 'mistral-small-latest', 'pixtral-large-latest'],
            self::Groq => ['meta-llama/llama-4-scout-17b-16e-instruct', 'meta-llama/llama-4-maverick-17b-128e-instruct', 'llama-3.3-70b-versatile'],
            self::DeepSeek => ['deepseek-chat', 'deepseek-reasoner'],
            self::OpenRouter => ['openai/gpt-4.1-mini', 'google/gemini-2.5-flash', 'meta-llama/llama-4-scout'],
            // Solo modelos de pago: los gratuitos de OpenCode Console solo
            // responden dentro de la app OpenCode, no a una API key suelta.
            self::OpenCode => ['claude-opus-5-5', 'claude-sonnet-5-5', 'gpt-5.4-mini', 'gemini-3.5-flash', 'kimi-k2.6', 'glm-5.3', 'deepseek-v4-pro', 'qwen3.8-max'],
            self::Ollama => ['llama3.2-vision', 'qwen2.5vl', 'gemma3'],
            self::Custom => [],
        };
    }

    /**
     * Ollama local no pide clave, y un endpoint propio puede no pedirla.
     */
    public function requiresApiKey(): bool
    {
        return ! in_array($this, [self::Ollama, self::Custom], true);
    }

    /**
     * Proveedores que apuntan a un servidor que elige el usuario. Hacen que la
     * app abra conexiones a direcciones arbitrarias (SSRF), así que solo se
     * ofrecen si el operador lo permite con AI_ALLOW_CUSTOM_ENDPOINTS.
     */
    public function requiresBaseUrl(): bool
    {
        return in_array($this, [self::Ollama, self::Custom], true);
    }

    /**
     * Endpoints fijos entre los que el usuario puede elegir (p. ej. el plan de
     * OpenCode). A diferencia de `requiresBaseUrl()`, la URL nunca la escribe
     * el usuario: solo se acepta una de estas, así que no abre la puerta a
     * conexiones arbitrarias.
     *
     * @return array<string, string> clave estable => URL base
     */
    public function endpointOptions(): array
    {
        return match ($this) {
            self::OpenCode => [
                'payg' => 'https://opencode.ai/inference/openai/v1',
                'go' => 'https://opencode.ai/inference/go/openai/v1',
            ],
            default => [],
        };
    }

    /**
     * Si el proveedor acepta imágenes. Depende también del modelo elegido: si
     * el modelo no las admite, el proveedor responde con un error que se
     * muestra tal cual.
     */
    public function supportsVision(): bool
    {
        return $this !== self::DeepSeek;
    }

    /**
     * Si acepta PDF de forma nativa (sin convertirlo antes a imagen).
     */
    public function supportsPdf(): bool
    {
        return in_array($this, [self::OpenAi, self::Anthropic, self::Gemini, self::OpenRouter], true);
    }

    /**
     * Modo JSON nativo (`response_format: json_object`) en el driver OpenAI.
     * Se limita a quien lo soporta con seguridad: el resto recibe la orden en
     * el prompt y la respuesta se analiza de forma tolerante igualmente.
     */
    public function supportsJsonMode(): bool
    {
        return in_array($this, [self::OpenAi, self::Mistral], true);
    }

    /**
     * OpenAI rechaza `max_tokens` en sus modelos de razonamiento; el resto de
     * proveedores compatibles solo entienden `max_tokens`.
     */
    public function usesMaxCompletionTokens(): bool
    {
        return $this === self::OpenAi;
    }

    public function apiKeyUrl(): ?string
    {
        return match ($this) {
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::Mistral => 'https://console.mistral.ai/api-keys',
            self::Groq => 'https://console.groq.com/keys',
            self::DeepSeek => 'https://platform.deepseek.com/api_keys',
            self::OpenRouter => 'https://openrouter.ai/keys',
            self::OpenCode => 'https://opencode.ai/docs/console/harnesses/',
            self::Ollama, self::Custom => null,
        };
    }

    /**
     * Si el operador de la instancia permite este proveedor.
     */
    public function isAvailable(): bool
    {
        return ! $this->requiresBaseUrl() || (bool) config('ai.allow_custom_endpoints', false);
    }
}
