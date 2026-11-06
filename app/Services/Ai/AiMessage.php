<?php

namespace App\Services\Ai;

/**
 * Mensaje en un formato neutro que cada driver traduce al de su proveedor.
 *
 * `raw` guarda el mensaje del asistente tal como lo devolvió el proveedor.
 * Dentro de un bucle de herramientas hay que reenviárselo sin tocar: Anthropic
 * exige recibir de vuelta sus bloques de razonamiento y Gemini sus firmas de
 * pensamiento; reconstruirlo a partir del texto los perdería.
 */
final class AiMessage
{
    public const USER = 'user';

    public const ASSISTANT = 'assistant';

    public const TOOL = 'tool';

    /**
     * @param  list<AiAttachment>  $attachments
     * @param  list<AiToolCall>  $toolCalls
     */
    public function __construct(
        public readonly string $role,
        public readonly string $text = '',
        public readonly array $attachments = [],
        public readonly array $toolCalls = [],
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolName = null,
        public readonly mixed $raw = null,
        public readonly ?string $rawDriver = null,
    ) {}

    /**
     * @param  list<AiAttachment>  $attachments
     */
    public static function user(string $text, array $attachments = []): self
    {
        return new self(self::USER, $text, $attachments);
    }

    public static function assistant(string $text): self
    {
        return new self(self::ASSISTANT, $text);
    }

    public static function fromResponse(AiResponse $response): self
    {
        return new self(
            self::ASSISTANT,
            $response->text,
            toolCalls: $response->toolCalls,
            raw: $response->raw,
            rawDriver: $response->driver,
        );
    }

    /**
     * @param  array<mixed>  $result
     */
    public static function toolResult(AiToolCall $call, array $result): self
    {
        return new self(
            self::TOOL,
            (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            toolCallId: $call->id,
            toolName: $call->name,
        );
    }
}
