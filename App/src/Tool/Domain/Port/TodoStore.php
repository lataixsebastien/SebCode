<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\TodoItem;

/**
 * Holds the agent's current todo list for the running session.
 *
 * Full-list semantics, mirroring opencode's `todowrite`: each write replaces
 * the whole list. The in-memory implementation is process-lifetime (one
 * process serves one session today); persistence keyed by session is a future
 * upgrade.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/todo.ts
 */
interface TodoStore
{
    /**
     * @param list<TodoItem> $todos
     */
    public function replace(array $todos): void;

    /**
     * @return list<TodoItem>
     */
    public function all(): array;
}
