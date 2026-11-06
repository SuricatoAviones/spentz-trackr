<?php

namespace App\Services\Ai;

final class AiRequest
{
    /**
     * @param  list<AiMessage>  $messages
     * @param  list<AiTool>  $tools
     */
    public function __construct(
        public readonly string $system,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly bool $json = false,
        public readonly int $maxTokens = 8000,
        public readonly ?int $timeout = null,
    ) {}

    /**
     * @param  list<AiMessage>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($this->system, $messages, $this->tools, $this->json, $this->maxTokens, $this->timeout);
    }

    public function hasAttachments(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->attachments !== []) {
                return true;
            }
        }

        return false;
    }
}
