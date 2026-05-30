<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

/**
 * Structured tail attached to a `Message` to carry tool-calling metadata that
 * doesn't fit naturally into the free-form `content` text.
 *
 * Two kinds for now:
 *
 *  - `Kind::ToolCall`   — emitted by the assistant when it asks for one or
 *                          more tools to be invoked. `toolCalls` is the list
 *                          of `ToolCallRequest` objects the LLM produced.
 *  - `Kind::ToolResult` — appended after a tool ran. Carries the originating
 *                          `toolCallId`, the (textual) output of the tool, and
 *                          an `isError` flag distinguishing soft failures from
 *                          successful invocations.
 *
 * The whole object round-trips to a `payload_json` TEXT column in
 * `assistant_messages` (see `MessageMapper`). See ADR-0006 for why a JSON
 * column was preferred over a fully relational Parts redesign.
 */
final readonly class MessagePayload
{
    /**
     * @param list<ToolCallRequest> $toolCalls populated when kind = ToolCall
     * @param string|null $toolCallId populated when kind = ToolResult
     * @param string|null $toolName populated when kind = ToolResult
     * @param string|null $toolOutput populated when kind = ToolResult
     */
    private function __construct(
        public MessagePayloadKind $kind,
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $toolName = null,
        public ?string $toolOutput = null,
        public bool $isError = false,
    ) {
    }

    /**
     * @param list<ToolCallRequest> $toolCalls
     */
    public static function ofToolCalls(array $toolCalls): self
    {
        if ([] === $toolCalls) {
            throw new \InvalidArgumentException('MessagePayload::ofToolCalls requires at least one tool call.');
        }

        return new self(MessagePayloadKind::ToolCall, toolCalls: $toolCalls);
    }

    public static function ofToolResult(
        string $toolCallId,
        string $toolName,
        string $output,
        bool $isError = false,
    ): self {
        if ('' === $toolCallId) {
            throw new \InvalidArgumentException('MessagePayload tool_call_id cannot be empty.');
        }
        if ('' === $toolName) {
            throw new \InvalidArgumentException('MessagePayload tool_name cannot be empty.');
        }

        return new self(
            MessagePayloadKind::ToolResult,
            toolCallId: $toolCallId,
            toolName: $toolName,
            toolOutput: $output,
            isError: $isError,
        );
    }
}
