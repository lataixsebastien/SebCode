<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Port\MessageRepository;

final readonly class GetSessionMessagesHandler
{
    public function __construct(private MessageRepository $messages)
    {
    }

    /**
     * @return list<Message>
     */
    public function __invoke(GetSessionMessagesQuery $query): array
    {
        return $this->messages->forSession($query->sessionId);
    }
}
