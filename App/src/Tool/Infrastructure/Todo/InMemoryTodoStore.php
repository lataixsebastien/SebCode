<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Todo;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Port\TodoStore;

/**
 * Process-lifetime todo lists, kept in memory and keyed by session.
 *
 * Shared (singleton) so the list written by one `todowrite` call is visible to
 * the next within the same session/run. Used in unit tests and as the default
 * binding; the Doctrine store persists across runs.
 */
final class InMemoryTodoStore implements TodoStore
{
    /**
     * @var array<string, list<TodoItem>>
     */
    private array $todos = [];

    public function replace(string $sessionId, array $todos): void
    {
        $this->todos[$sessionId] = $todos;
    }

    public function all(string $sessionId): array
    {
        return $this->todos[$sessionId] ?? [];
    }
}
