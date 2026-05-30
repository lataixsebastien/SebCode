<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessagePayload;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;

final readonly class Message
{
    public function __construct(
        public MessageId $id,
        public SessionId $sessionId,
        public MessageRole $role,
        public MessageContent $content,
        public \DateTimeImmutable $createdAt,
        public ?MessagePayload $payload = null,
    ) {
    }
}
