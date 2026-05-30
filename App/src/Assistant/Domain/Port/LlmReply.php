<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;

/**
 * What the LLM returned for one round-trip.
 *
 * Either textual (`content` populated, `toolCalls` empty) or a request to
 * invoke one or more tools (`toolCalls` non-empty). In practice providers
 * may also stream `content` plus `toolCalls` together — the Assistant loop
 * always honors the tool calls first and only stops on a tool-free reply.
 */
final readonly class LlmReply
{
    /**
     * @param list<ToolCallRequest> $toolCalls
     */
    public function __construct(
        public string $content,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public array $toolCalls = [],
    ) {
    }
}
