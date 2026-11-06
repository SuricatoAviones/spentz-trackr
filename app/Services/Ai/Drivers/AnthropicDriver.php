<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiAttachment;
use App\Services\Ai\AiException;
use App\Services\Ai\AiMessage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AiTool;
use App\Services\Ai\AiToolCall;
use Illuminate\Http\Client\Response;
use stdClass;

/**
 * Protocolo Anthropic Messages (`POST /v1/messages`).
 *
 * Detalles que importan con los modelos actuales:
 *  - No se manda `temperature` ni prefill del asistente: los modelos 4.6+ y
 *    la familia 5 los rechazan con un 400.
 *  - Tampoco `thinking`: en Opus 5.5 el razonamiento no se puede apagar y
 *    omitir el parámetro es la forma correcta. Sus bloques `thinking` llegan
 *    en `content` y se reenvían intactos dentro del bucle de herramientas.
 *  - Los resultados de herramientas van todos juntos en UN mensaje de usuario.
 *  - En los modelos que lo admiten se activa el `fallbacks: "default"` del
 *    servidor: si el modelo rechaza la petición, la API la reintenta con otro.
 */
class AnthropicDriver extends AiDriver
{
    private const API_VERSION = '2023-06-01';

    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /** Modelos que aceptan `fallbacks: "default"` en la API de Claude. */
    private const FALLBACK_MODELS = ['claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    public function name(): string
    {
        return 'anthropic';
    }

    protected function dispatch(AiRequest $request): Response
    {
        $body = [
            'model' => $this->model(),
            'max_tokens' => max(1024, $request->maxTokens),
            'system' => $request->system,
            'messages' => $this->messages($request),
        ];

        if ($request->tools !== []) {
            $body['tools'] = array_map(fn (AiTool $tool): array => [
                'name' => $tool->name,
                'description' => $tool->description,
                'input_schema' => $tool->schema(),
            ], $request->tools);
        }

        $headers = [
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => self::API_VERSION,
        ];

        if (in_array($this->model(), self::FALLBACK_MODELS, true)) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
            $body['fallbacks'] = 'default';
        }

        return $this->http($request)->withHeaders($headers)->post($this->url('messages'), $body);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function messages(AiRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            if ($message->role === AiMessage::TOOL) {
                $block = [
                    'type' => 'tool_result',
                    'tool_use_id' => (string) $message->toolCallId,
                    'content' => $message->text,
                ];

                $last = array_key_last($messages);

                if ($last !== null && ($messages[$last]['tool_results'] ?? false) === true) {
                    $messages[$last]['content'][] = $block;
                } else {
                    $messages[] = ['role' => 'user', 'content' => [$block], 'tool_results' => true];
                }

                continue;
            }

            $messages[] = $message->role === AiMessage::USER
                ? ['role' => 'user', 'content' => $this->userContent($message)]
                : ['role' => 'assistant', 'content' => $this->assistantContent($message)];
        }

        // `tool_results` solo marcaba qué mensaje agrupa resultados; no viaja.
        return array_map(fn (array $message): array => array_diff_key($message, ['tool_results' => true]), $messages);
    }

    /**
     * @return string|list<array<string, mixed>>
     */
    private function userContent(AiMessage $message): string|array
    {
        if ($message->attachments === []) {
            return $message->text;
        }

        // Los documentos van antes del texto: así lo recomienda Anthropic.
        $blocks = array_map(fn (AiAttachment $attachment): array => [
            'type' => $attachment->isPdf() ? 'document' : 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $attachment->mimeType,
                'data' => $attachment->base64,
            ],
        ], $message->attachments);

        $blocks[] = ['type' => 'text', 'text' => $message->text];

        return $blocks;
    }

    private function assistantContent(AiMessage $message): mixed
    {
        if ($message->rawDriver === $this->name() && is_array($message->raw)) {
            return $message->raw;
        }

        if ($message->toolCalls === []) {
            return $message->text;
        }

        $blocks = $message->text !== '' ? [['type' => 'text', 'text' => $message->text]] : [];

        foreach ($message->toolCalls as $call) {
            $blocks[] = [
                'type' => 'tool_use',
                'id' => $call->id,
                'name' => $call->name,
                'input' => $call->arguments === [] ? new stdClass : $call->arguments,
            ];
        }

        return $blocks;
    }

    protected function parse(array $payload, mixed $objectPayload): AiResponse
    {
        if (($payload['stop_reason'] ?? null) === 'refusal') {
            throw AiException::because(AiException::REFUSED);
        }

        $content = $payload['content'] ?? null;

        if (! is_array($content)) {
            throw AiException::because(AiException::INVALID_RESPONSE);
        }

        $text = '';
        $toolCalls = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = new AiToolCall(
                    (string) ($block['id'] ?? ''),
                    (string) ($block['name'] ?? ''),
                    is_array($block['input'] ?? null) ? $block['input'] : [],
                );
            }
        }

        $raw = is_object($objectPayload) && isset($objectPayload->content) && is_array($objectPayload->content)
            ? $objectPayload->content
            : null;

        return new AiResponse(
            text: $this->cleanText($text),
            toolCalls: $toolCalls,
            inputTokens: (int) ($payload['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($payload['usage']['output_tokens'] ?? 0),
            raw: $raw,
            driver: $this->name(),
        );
    }
}
