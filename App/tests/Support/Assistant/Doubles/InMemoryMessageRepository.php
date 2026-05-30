<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\MessageRepository;

final class InMemoryMessageRepository implements MessageRepository
{
    /** @var list<Message> */
    private array $messages = [];

    public function append(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return list<Message>
     */
    public function forSession(SessionId $sessionId): array
    {
        $filtered = array_filter(
            $this->messages,
            static fn (Message $m): bool => $m->sessionId->equals($sessionId),
        );

        $list = array_values($filtered);
        usort($list, static fn (Message $a, Message $b): int => $a->createdAt <=> $b->createdAt);

        return $list;
    }
}
