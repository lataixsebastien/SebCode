<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;

/**
 * One task in the agent's todo list.
 *
 * Faithful to opencode: just content + status + priority, no synthetic id —
 * items are identified by their position in the list.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/session/todo.ts
 */
final readonly class TodoItem
{
    public function __construct(
        public string $content,
        public TodoStatus $status,
        public TodoPriority $priority,
    ) {
        if ('' === trim($content)) {
            throw new \InvalidArgumentException('TodoItem content cannot be empty.');
        }
    }
}
