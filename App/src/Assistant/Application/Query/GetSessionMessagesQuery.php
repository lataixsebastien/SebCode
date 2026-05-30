<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

use App\Assistant\Domain\Model\ValueObject\SessionId;

final readonly class GetSessionMessagesQuery
{
    public function __construct(public SessionId $sessionId)
    {
    }
}
