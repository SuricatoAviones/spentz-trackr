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
 * Protocolo Google Gemini (`models/{model}:generateContent`).
 *
 * Las partes del modelo se reenvían en crudo dentro del bucle de herramientas
 * porque llevan `thoughtSignature`: si se reconstruyen, los modelos con
 * razonamiento rechazan la llamada siguiente.
 */
class GeminiDriver extends AiDriver
{
    /** Motivos de corte que equivalen a un rechazo por política. */
    private const BLOCKED = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function name(): string
    {
        return 'gemini';
    }

    protected function dispatch(AiRequest $request): Response
    {
        $model = preg_replace('#^models/#', '', $this->model()) ?? $this->model();

        $body = [
            'systemInstruction' => ['parts' => [['text' => $request->system]]],
            'contents' => $this->contents($request),
            'generationConfig' => ['maxOutputTokens' => $request->maxTokens],
        ];

        if ($request->tools !== []) {
            $body['tools'] = [[
                'functionDeclarations' => array_map(function (AiTool $tool): array {
                    $declaration = ['name' => $tool->name, 'description' => $tool->description];

                    // Gemini no acepta un objeto sin propiedades: se omite.
                    if ($tool->hasParameters()) {
                        $declaration['parameters'] = $tool->schema();
                    }

                    return $declaration;
                }, $request->tools),
            ]];
        } elseif ($request->json) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        return $this->http()
            ->withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->post($this->url('models/'.$model.':generateContent'), $body);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function contents(AiRequest $request): array
    {
        $contents = [];

        foreach ($request->messages as $message) {
            if ($message->role === AiMessage::TOOL) {
                $decoded = json_decode($message->text, false);

                $response = [
                    'name' => (string) $message->toolName,
                    'response' => ['result' => $decoded ?? $message->text],
                ];

                if ($message->toolCallId !== null && ! str_starts_with($message->toolCallId, 'gemini_')) {
                    $response['id'] = $message->toolCallId;
                }

                $last = array_key_last($contents);

                if ($last !== null && ($contents[$last]['function_responses'] ?? false) === true) {
                    $contents[$last]['parts'][] = ['functionResponse' => $response];
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [['functionResponse' => $response]], 'function_responses' => true];
                }

                continue;
            }

            $contents[] = $message->role === AiMessage::USER
                ? ['role' => 'user', 'parts' => $this->userParts($message)]
                : ['role' => 'model', 'parts' => $this->modelParts($message)];
        }

        // `function_responses` solo marcaba qué contenido agrupa respuestas.
        return array_map(fn (array $content): array => array_diff_key($content, ['function_responses' => true]), $contents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function userParts(AiMessage $message): array
    {
        $parts = array_map(fn (AiAttachment $attachment): array => [
            'inlineData' => ['mimeType' => $attachment->mimeType, 'data' => $attachment->base64],
        ], $message->attachments);

        $parts[] = ['text' => $message->text];

        return $parts;
    }

    private function modelParts(AiMessage $message): mixed
    {
        if ($message->rawDriver === $this->name() && is_array($message->raw)) {
            return $message->raw;
        }

        $parts = $message->text !== '' ? [['text' => $message->text]] : [];

        foreach ($message->toolCalls as $call) {
            $parts[] = ['functionCall' => [
                'name' => $call->name,
                'args' => $call->arguments === [] ? new stdClass : $call->arguments,
            ]];
        }

        return $parts === [] ? [['text' => '']] : $parts;
    }

    protected function isInvalidKey(int $status, string $detail): bool
    {
        // Gemini responde 400 INVALID_ARGUMENT ante una clave mala.
        return parent::isInvalidKey($status, $detail)
            || ($status === 400 && stripos($detail, 'API key') !== false);
    }

    protected function parse(array $payload, mixed $objectPayload): AiResponse
    {
        $candidate = $payload['candidates'][0] ?? null;

        if (! is_array($candidate)) {
            if (isset($payload['promptFeedback']['blockReason'])) {
                throw AiException::because(AiException::REFUSED);
            }

            throw AiException::because(AiException::INVALID_RESPONSE);
        }

        $text = '';
        $toolCalls = [];

        foreach (($candidate['content']['parts'] ?? []) as $index => $part) {
            if (! is_array($part)) {
                continue;
            }

            if (isset($part['text']) && ($part['thought'] ?? false) !== true) {
                $text .= (string) $part['text'];
            }

            if (isset($part['functionCall']['name'])) {
                $toolCalls[] = new AiToolCall(
                    (string) ($part['functionCall']['id'] ?? 'gemini_'.$index),
                    (string) $part['functionCall']['name'],
                    is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [],
                );
            }
        }

        if ($text === '' && $toolCalls === [] && in_array($candidate['finishReason'] ?? null, self::BLOCKED, true)) {
            throw AiException::because(AiException::REFUSED);
        }

        $raw = $objectPayload->candidates[0]->content->parts ?? null;

        return new AiResponse(
            text: $this->cleanText($text),
            toolCalls: $toolCalls,
            inputTokens: (int) ($payload['usageMetadata']['promptTokenCount'] ?? 0),
            outputTokens: (int) ($payload['usageMetadata']['candidatesTokenCount'] ?? 0),
            raw: is_array($raw) ? $raw : null,
            driver: $this->name(),
        );
    }
}
