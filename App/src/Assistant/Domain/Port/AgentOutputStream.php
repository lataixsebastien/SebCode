<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

/**
 * Live output sink for the agent loop: the loop reports the assistant's text
 * (streamed chunk by chunk) and each tool call/result as they happen, so a UI
 * can render progress in real time instead of only after the turn completes.
 *
 * A UI attaches its sink via {@see AgentOutputStreamRegistry}; when none is
 * attached the loop drives a no-op sink and behaves exactly as before.
 */
interface AgentOutputStream
{
    public function assistantText(string $delta): void;

    /**
     * @param array<string, mixed> $arguments
     */
    public function toolCall(string $name, array $arguments): void;

    public function toolResult(string $name, string $output, bool $isError): void;
}
