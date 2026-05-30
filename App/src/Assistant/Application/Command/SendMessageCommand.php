<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command;

use App\Assistant\Domain\Model\ValueObject\SessionId;

final readonly class SendMessageCommand
{
    public function __construct(
        public SessionId $sessionId,
        public string $userText,
    ) {
    }
}
