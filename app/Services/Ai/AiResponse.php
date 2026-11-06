<?php

namespace App\Services\Ai;

final class AiResponse
{
    /**
     * @param  list<AiToolCall>  $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly mixed $raw = null,
        public readonly ?string $driver = null,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
