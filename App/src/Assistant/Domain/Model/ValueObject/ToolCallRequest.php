<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

/**
 * Tool invocation requested by the LLM in its reply.
 *
 * Mirror of `Tool\Domain\Model\ToolCall` kept inside `Assistant\Domain` to
 * avoid cross-context coupling (see ADR-0004).
 */
final readonly class ToolCallRequest
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('ToolCallRequest id cannot be empty.');
        }
        if ('' === $name) {
            throw new \InvalidArgumentException('ToolCallRequest name cannot be empty.');
        }
    }
}
