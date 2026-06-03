<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Lifecycle state of a {@see \App\Tool\Domain\Model\TodoItem}.
 *
 * Mirrors opencode's todo states. "exactly one in_progress at a time" is a
 * behavioural rule guided by the tool description, not a hard constraint.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/session/todo.ts
 */
enum TodoStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
