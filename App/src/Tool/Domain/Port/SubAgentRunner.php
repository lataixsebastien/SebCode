<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

/**
 * Runs a bounded, ephemeral nested agent loop for the `task` tool.
 *
 * The implementation lives in the Assistant context (it needs the LLM and the
 * tool loop) and is reached only through this Tool-domain port — the second
 * sanctioned Assistant↔Tool bridge after {@see \App\Assistant\Domain\Port\ToolGateway}.
 * The sub-agent gets the normal tools EXCEPT `task` (no recursion); its work is
 * not persisted and only its final text is returned.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/task.ts
 */
interface SubAgentRunner
{
    /**
     * @param string $sessionId the parent session, used to scope nested tool calls
     *
     * @return string the sub-agent's final textual answer
     */
    public function run(string $instruction, string $sessionId): string;
}
