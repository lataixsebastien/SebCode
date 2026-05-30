<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\ToolCallId;

/**
 * Outcome of executing a `ToolCall`.
 *
 * `isError` is a soft flag — the assistant loop keeps going and feeds the
 * error string back to the LLM so it can correct its next turn. A hard
 * failure (registry missing, infrastructure down) raises an exception
 * instead and aborts the loop.
 */
final readonly class ToolResult
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public ToolCallId $callId,
        public string $output,
        public bool $isError = false,
        public ?array $metadata = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public static function success(ToolCallId $callId, string $output, ?array $metadata = null): self
    {
        return new self($callId, $output, false, $metadata);
    }

    public static function failure(ToolCallId $callId, string $reason): self
    {
        return new self($callId, $reason, true, null);
    }
}
