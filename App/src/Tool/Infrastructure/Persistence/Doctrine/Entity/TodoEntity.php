<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine row for one todo item, keyed by (session_id, position).
 *
 * No FK to the Assistant `assistant_sessions` table on purpose — the Tool
 * context stays decoupled from the Assistant schema; `session_id` is just an
 * indexed string. `todowrite` replaces the whole list per session, so the
 * position carries the order and there is no need for a surrogate id.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tool_todos')]
#[ORM\Index(name: 'idx_todo_session', columns: ['session_id'])]
class TodoEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'session_id', type: 'string', length: 64)]
    public string $sessionId;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $position;

    #[ORM\Column(type: 'text')]
    public string $content;

    #[ORM\Column(type: 'string', length: 16)]
    public string $status;

    #[ORM\Column(type: 'string', length: 16)]
    public string $priority;
}
