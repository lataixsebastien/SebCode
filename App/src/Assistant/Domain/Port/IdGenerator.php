<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\SessionId;

interface IdGenerator
{
    public function nextSessionId(): SessionId;

    public function nextMessageId(): MessageId;
}
