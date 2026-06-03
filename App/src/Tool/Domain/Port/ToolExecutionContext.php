<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

/**
 * Runtime context handed to every tool execution.
 *
 * Carries everything a tool needs to enforce its sandbox without reaching
 * into globals: the project root any path argument must stay inside, the
 * output budget, the current time (for deterministic tests), and the id of
 * the session the call belongs to (for session-scoped tools like todowrite).
 */
final readonly class ToolExecutionContext
{
    public function __construct(
        public string $projectRoot,
        public int $maxOutputBytes,
        public \DateTimeImmutable $now,
        public string $sessionId = '',
    ) {
        if ('' === $projectRoot) {
            throw new \InvalidArgumentException('ToolExecutionContext projectRoot cannot be empty.');
        }
        if ($maxOutputBytes <= 0) {
            throw new \InvalidArgumentException('ToolExecutionContext maxOutputBytes must be positive.');
        }
    }
}
