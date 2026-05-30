<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Id;

use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Port\IdGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * IdGenerator implementation backed by Symfony's UUID v7.
 *
 * UUIDv7 is time-ordered, so sequential ids sort chronologically — useful
 * for chronological listing of sessions/messages without a separate index.
 *
 * Format: <prefix><uuid-v7 in toBase58 form>, e.g. "ses_4WuV5GpwGsM7..."
 */
final class SymfonyUidGenerator implements IdGenerator
{
    public function nextSessionId(): SessionId
    {
        return SessionId::fromString(SessionId::PREFIX.Uuid::v7()->toBase58());
    }

    public function nextMessageId(): MessageId
    {
        return MessageId::fromString(MessageId::PREFIX.Uuid::v7()->toBase58());
    }
}
