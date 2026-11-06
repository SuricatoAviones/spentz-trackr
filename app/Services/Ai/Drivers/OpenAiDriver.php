<?php

namespace App\Services\Ai\Drivers;

use App\Enums\AiProvider;
use App\Services\Ai\AiAttachment;
use App\Services\Ai\AiException;
use App\Services\Ai\AiMessage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;
use App\Services\Ai\AiTool;
use App\Services\Ai\AiToolCall;
use Illuminate\Http\Client\Response;

/**
 * Protocolo OpenAI Chat Completions. Lo hablan OpenAI, Mistral, Groq,
 * DeepSeek, OpenRouter, Ollama y cualquier servidor "OpenAI-compatible".
 *
 * El mensaje del asistente se reconstruye en vez de reenviarse en crudo: hay
 * compatibles (DeepSeek) que devuelven campos como `reasoning_content` y luego
 * rechazan con un 400 recibirlos de vuelta.
 */
class OpenAiDriver extends AiDriver
{
    public function name(): string
    {
        return 'openai';
    }

    protected function dispatch(AiRequest $request): Response
    {
        $provider = $this->setting->provider;

        $body = [
            'model' => $this->model(),
            'messages' => $this->messages($request),
        ];

        $body[$provider->usesMaxCompletionTokens() ? 'max_completion_tokens' : 'max_tokens'] = $request->maxTokens;

        if ($request->tools !== []) {
            $body['tools'] = array_map(fn (AiTool $tool): array => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->schema(),
                ],
            ], $request->tools);
        }

        if ($request->json && $request->tools === [] && $provider->supportsJsonMode()) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $http = $this->http();

        if ($this->apiKey() !== '') {
            $http = $http->withToken($this->apiKey());
        }

        if ($provider === AiProvider::OpenRouter) {
            $http = $http->withHeaders([
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name'),
            ]);
        }

        return $http->post($this->url('chat/completions'), $body);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function messages(AiRequest $request): array
    {
        $messages = [['role' => 'system', 'content' => $request->system]];

        foreach ($request->messages as $message) {
            $messages[] = match ($message->role) {
                AiMessage::USER => $this->userMessage($message),
                AiMessage::ASSISTANT => $this->assistantMessage($message),
                default => $this->toolMessage($message),
            };
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private function userMessage(AiMessage $message): array
    {
        if ($message->attachments === []) {
            return ['role' => 'user', 'content' => $message->text];
        }

        $parts = array_map(fn (AiAttachment $attachment): array => $attachment->isPdf()
            ? ['type' => 'file', 'file' => ['filename' => $attachment->name, 'file_data' => $attachment->dataUrl()]]
            : ['type' => 'image_url', 'image_url' => ['url' => $attachment->dataUrl()]],
            $message->attachments,
        );

        $parts[] = ['type' => 'text', 'text' => $message->text];

        return ['role' => 'user', 'content' => $parts];
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantMessage(AiMessage $message): array
    {
        if ($message->toolCalls === []) {
            return ['role' => 'assistant', 'content' => $message->text];
        }

        return [
            'role' => 'assistant',
            'content' => $message->text !== '' ? $message->text : null,
            'tool_calls' => array_map(fn (AiToolCall $call): array => [
                'id' => $call->id,
                'type' => 'function',
                'function' => [
                    'name' => $call->name,
                    'arguments' => $call->arguments === [] ? '{}' : (string) json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                ],
            ], $message->toolCalls),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolMessage(AiMessage $message): array
    {
        $result = [
            'role' => 'tool',
            'tool_call_id' => (string) $message->toolCallId,
            'content' => $message->text,
        ];

        // Mistral exige el nombre de la función en el resultado; OpenAI no lo
        // espera, así que solo se añade donde hace falta.
        if ($this->setting->provider === AiProvider::Mistral) {
            $result['name'] = (string) $message->toolName;
        }

        return $result;
    }

    protected function parse(array $payload, mixed $objectPayload): AiResponse
    {
        if (isset($payload['error'])) {
            throw AiException::because(AiException::BAD_REQUEST, is_array($payload['error']) ? (string) ($payload['error']['message'] ?? '') : (string) $payload['error']);
        }

        $choice = $payload['choices'][0] ?? null;
        $message = is_array($choice) ? ($choice['message'] ?? null) : null;

        if (! is_array($message)) {
            throw AiException::because(AiException::INVALID_RESPONSE);
        }

        $text = $this->cleanText($this->contentText($message['content'] ?? null));
        $toolCalls = [];

        foreach (($message['tool_calls'] ?? []) as $index => $call) {
            if (! is_array($call) || ! isset($call['function']['name'])) {
                continue;
            }

            $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);

            $toolCalls[] = new AiToolCall(
                (string) ($call['id'] ?? 'call_'.$index),
                (string) $call['function']['name'],
                is_array($arguments) ? $arguments : [],
            );
        }

        if ($text === '' && $toolCalls === [] && ($choice['finish_reason'] ?? null) === 'content_filter') {
            throw AiException::because(AiException::REFUSED);
        }

        return new AiResponse(
            text: $text,
            toolCalls: $toolCalls,
            inputTokens: (int) ($payload['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($payload['usage']['completion_tokens'] ?? 0),
            driver: $this->name(),
        );
    }

    /**
     * `content` suele ser texto, pero algunos compatibles devuelven una lista
     * de partes `{type: text, text}`.
     */
    private function contentText(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $text = '';

        foreach ($content as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }
}
