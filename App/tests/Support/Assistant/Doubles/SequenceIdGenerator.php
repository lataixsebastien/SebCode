<?php

declare(strict_types=1);

namespace App\Tests\Support\Assistant\Doubles;

use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\IdGenerator;

final class SequenceIdGenerator implements IdGenerator
{
    private int $sessionSeq = 0;
    private int $messageSeq = 0;

    public function nextSessionId(): SessionId
    {
        ++$this->sessionSeq;

        return SessionId::fromString(\sprintf('ses_%03d', $this->sessionSeq));
    }

    public function nextMessageId(): MessageId
    {
        ++$this->messageSeq;

        return MessageId::fromString(\sprintf('msg_%03d', $this->messageSeq));
    }
}
