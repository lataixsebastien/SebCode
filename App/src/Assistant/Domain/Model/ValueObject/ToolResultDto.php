<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

/**
 * Outcome of a tool invocation, as carried back to the LLM through the
 * Assistant Application layer.
 *
 * Mirror of `Tool\Domain\Model\ToolResult` (see ADR-0004). Soft failures
 * are conveyed by `isError=true` + descriptive `output`; hard failures
 * never reach this DTO — they propagate as exceptions to abort the loop.
 */
final readonly class ToolResultDto
{
    public function __construct(
        public string $toolCallId,
        public string $output,
        public bool $isError = false,
    ) {
        if ('' === $toolCallId) {
            throw new \InvalidArgumentException('ToolResultDto toolCallId cannot be empty.');
        }
    }

    public static function success(string $toolCallId, string $output): self
    {
        return new self($toolCallId, $output, false);
    }

    public static function error(string $toolCallId, string $reason): self
    {
        return new self($toolCallId, $reason, true);
    }
}
