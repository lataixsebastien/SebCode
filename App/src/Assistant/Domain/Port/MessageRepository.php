<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\SessionId;

interface MessageRepository
{
    public function append(Message $message): void;

    /**
     * @return list<Message> ordered by createdAt ASC
     */
    public function forSession(SessionId $sessionId): array;
}
