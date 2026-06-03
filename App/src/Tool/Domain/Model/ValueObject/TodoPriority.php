<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Priority of a {@see \App\Tool\Domain\Model\TodoItem}. Mirrors opencode.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/session/todo.ts
 */
enum TodoPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
