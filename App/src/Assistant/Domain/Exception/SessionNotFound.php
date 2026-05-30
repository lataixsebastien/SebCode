<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Exception;

use App\Assistant\Domain\Model\ValueObject\SessionId;

final class SessionNotFound extends \RuntimeException
{
    public static function withId(SessionId $id): self
    {
        return new self(\sprintf('Session "%s" not found.', $id->value));
    }
}
