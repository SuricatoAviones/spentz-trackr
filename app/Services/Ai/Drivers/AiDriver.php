<?php

namespace App\Services\Ai\Drivers;

use App\Models\AiSetting;
use App\Services\Ai\AiException;
use App\Services\Ai\AiMessage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Transporte hacia un proveedor de IA. Cada subclase traduce el formato neutro
 * (`AiRequest` / `AiMessage`) al de su protocolo y la respuesta de vuelta.
 *
 * Todas las peticiones salen con timeout: sin él, un proveedor colgado dejaba
 * el worker esperando indefinidamente (la misma lección que el sync de tasas,
 * ver `.ai/rules/controllers.md`).
 */
abstract class AiDriver
{
    public function __construct(protected readonly AiSetting $setting) {}

    abstract public function name(): string;

    abstract protected function dispatch(AiRequest $request): Response;

    /**
     * @param  array<string, mixed>  $payload  respuesta decodificada como arrays
     * @param  mixed  $objectPayload  la misma respuesta decodificada como objetos
     */
    abstract protected function parse(array $payload, mixed $objectPayload): AiResponse;

    public function send(AiRequest $request): AiResponse
    {
        $this->guardAttachments($request);

        $response = $this->call($request);

        if ($response->failed()) {
            $this->fail($response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw AiException::because(AiException::INVALID_RESPONSE);
        }

        // Una segunda decodificación conservando objetos: `{}` tiene que
        // volver al proveedor como `{}` y no como `[]` cuando se le reenvía su
        // propio mensaje dentro del bucle de herramientas.
        return $this->parse($payload, json_decode($response->body(), false));
    }

    /**
     * Comprueba que la clave, el modelo y el endpoint responden. Basta con un
     * 2xx: el contenido de la respuesta no importa.
     */
    public function verify(): void
    {
        $response = $this->call(new AiRequest(
            system: 'You are a connectivity check. Reply with the single word OK.',
            messages: [AiMessage::user('ping')],
            maxTokens: 512,
        ));

        if ($response->failed()) {
            $this->fail($response);
        }
    }

    protected function call(AiRequest $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (ConnectionException $exception) {
            throw AiException::because(AiException::TIMEOUT, previous: $exception);
        }
    }

    /**
     * Cliente HTTP con timeout. Una petición puede pedir más margen (leer un
     * estado de cuenta de varias páginas tarda más que una factura).
     */
    protected function http(?AiRequest $request = null): PendingRequest
    {
        $timeout = max(5, (int) config('ai.timeout', 60), (int) ($request->timeout ?? 0));

        return Http::timeout($timeout)
            ->connectTimeout(max(2, (int) config('ai.connect_timeout', 10)))
            ->acceptJson()
            ->asJson()
            ->withUserAgent('SpentzTrackr/'.config('app.version', '1'))
            // Un reintento ante sobrecarga momentánea del proveedor (Anthropic
            // devuelve 529). Nada más: cada intento puede tardar un minuto.
            ->retry(2, 800, function (mixed $exception): bool {
                return $exception instanceof RequestException
                    && in_array($exception->response->status(), [500, 502, 503, 529], true);
            }, throw: false);
    }

    protected function model(): string
    {
        return $this->setting->resolvedModel();
    }

    protected function apiKey(): string
    {
        return trim((string) $this->setting->api_key);
    }

    protected function url(string $path): string
    {
        return $this->setting->resolvedBaseUrl().'/'.ltrim($path, '/');
    }

    protected function guardAttachments(AiRequest $request): void
    {
        $provider = $this->setting->provider;

        foreach ($request->messages as $message) {
            foreach ($message->attachments as $attachment) {
                if (! $provider->supportsVision()) {
                    throw AiException::because(AiException::UNSUPPORTED_ATTACHMENT);
                }

                if ($attachment->isPdf() && ! $provider->supportsPdf()) {
                    throw new AiException(
                        AiException::UNSUPPORTED_ATTACHMENT,
                        __('messages.ai_error_pdf_unsupported'),
                    );
                }
            }
        }
    }

    protected function fail(Response $response): never
    {
        $status = $response->status();
        $detail = $this->providerMessage($response);

        $reason = match (true) {
            $this->isInvalidKey($status, $detail) => AiException::INVALID_KEY,
            $status === 404 => AiException::MODEL_NOT_FOUND,
            $status === 429 => AiException::RATE_LIMITED,
            in_array($status, [408, 504], true) => AiException::TIMEOUT,
            $status >= 500 => AiException::UNAVAILABLE,
            default => AiException::BAD_REQUEST,
        };

        $showDetail = in_array($reason, [AiException::BAD_REQUEST, AiException::MODEL_NOT_FOUND, AiException::RATE_LIMITED], true);

        throw AiException::because($reason, $showDetail ? $detail : null);
    }

    protected function isInvalidKey(int $status, string $detail): bool
    {
        return in_array($status, [401, 403], true);
    }

    /**
     * El mensaje de error del proveedor, recortado. Los tres protocolos usan
     * `error.message`; algunos compatibles mandan `error` como texto.
     */
    protected function providerMessage(Response $response): string
    {
        $error = $response->json('error');

        $message = match (true) {
            is_array($error) && is_string($error['message'] ?? null) => $error['message'],
            is_string($error) => $error,
            is_string($response->json('message')) => $response->json('message'),
            default => '',
        };

        return Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? ''), 240);
    }

    /**
     * Quita bloques `<think>…</think>` que algunos modelos de razonamiento
     * (DeepSeek, Qwen vía Ollama u OpenRouter) mezclan con la respuesta.
     */
    protected function cleanText(string $text): string
    {
        return trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));
    }
}
