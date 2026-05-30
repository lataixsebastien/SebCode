<?php

declare(strict_types=1);

namespace App\Tool\Application\Command;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\ToolNotFound;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Port\ToolRegistry;

/**
 * Resolve a `ToolCall` against the registry and execute it, turning any
 * recoverable failure into a `ToolResult::failure(...)` so the assistant
 * loop can feed it back to the LLM. Hard failures (registry missing the
 * tool) still propagate as exceptions — the caller decides whether to
 * abort or wrap them.
 */
final readonly class ExecuteToolHandler
{
    public function __construct(private ToolRegistry $registry)
    {
    }

    public function __invoke(ExecuteToolCommand $command): ToolResult
    {
        $tool = $this->registry->find($command->call->name);
        if (null === $tool) {
            throw ToolNotFound::withName($command->call->name);
        }

        try {
            return $tool->execute($command->call, $command->context);
        } catch (InvalidToolArguments $e) {
            return ToolResult::failure($command->call->id, $e->getMessage());
        }
    }
}
