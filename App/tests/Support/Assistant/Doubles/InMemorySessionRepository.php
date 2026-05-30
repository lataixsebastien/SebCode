<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\SessionRepository;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function save(Session $session): void
    {
        $this->sessions[$session->id->value] = $session;
    }

    public function findById(SessionId $id): ?Session
    {
        return $this->sessions[$id->value] ?? null;
    }

    /**
     * @return list<Session>
     */
    public function all(): array
    {
        return array_values($this->sessions);
    }

    public function delete(SessionId $id): void
    {
        unset($this->sessions[$id->value]);
    }
}
