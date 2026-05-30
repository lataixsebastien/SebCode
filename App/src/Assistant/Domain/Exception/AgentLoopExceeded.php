<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Exception;

/**
 * Raised when the assistant's tool-calling loop hits the configured turn cap
 * (e.g. the LLM keeps requesting tool invocations without ever finalizing).
 *
 * Treated as a hard stop by `SendMessageHandler`: the user message and every
 * intermediate tool exchange remain persisted so the operator can inspect or
 * resume the session, but the command surfaces an explicit failure.
 */
final class AgentLoopExceeded extends \RuntimeException
{
    public function __construct(public readonly int $maxTurns)
    {
        parent::__construct(\sprintf('Assistant tool-calling loop did not finalize within %d turns.', $maxTurns));
    }
}
