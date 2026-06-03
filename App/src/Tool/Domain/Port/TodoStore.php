<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\TodoItem;

/**
 * Holds the agent's current todo list for the running session.
 *
 * Full-list semantics, mirroring opencode's `todowrite`: each write replaces
 * the whole list for that session. Scoped by session id so a process serving
 * several sessions (or a persistent backend) keeps them apart.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/todo.ts
 */
interface TodoStore
{
    /**
     * @param list<TodoItem> $todos
     */
    public function replace(string $sessionId, array $todos): void;

    /**
     * @return list<TodoItem>
     */
    public function all(string $sessionId): array;
}
