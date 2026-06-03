<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Todo;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Port\TodoStore;

/**
 * Process-lifetime todo list, kept in memory.
 *
 * Shared (singleton) so the list written by one `todowrite` call is visible to
 * the next within the same run. One process serves one session today, so the
 * store is not keyed by session — see the port docblock.
 */
final class InMemoryTodoStore implements TodoStore
{
    /**
     * @var list<TodoItem>
     */
    private array $todos = [];

    public function replace(array $todos): void
    {
        $this->todos = $todos;
    }

    public function all(): array
    {
        return $this->todos;
    }
}
