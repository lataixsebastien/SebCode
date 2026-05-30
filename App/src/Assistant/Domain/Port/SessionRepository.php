<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\Session;
use App\Assistant\Domain\Model\ValueObject\SessionId;

interface SessionRepository
{
    public function save(Session $session): void;

    public function findById(SessionId $id): ?Session;

    /**
     * @return list<Session>
     */
    public function all(): array;

    public function delete(SessionId $id): void;
}
