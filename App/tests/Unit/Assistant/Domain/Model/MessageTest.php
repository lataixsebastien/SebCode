<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testHoldsAllReadonlyProperties(): void
    {
        $createdAt = new \DateTimeImmutable('2026-05-30T10:00:00+00:00');

        $message = new Message(
            MessageId::fromString('msg_1'),
            SessionId::fromString('ses_1'),
            MessageRole::User,
            MessageContent::of('hi'),
            $createdAt,
        );

        self::assertSame('msg_1', $message->id->value);
        self::assertSame('ses_1', $message->sessionId->value);
        self::assertSame(MessageRole::User, $message->role);
        self::assertSame('hi', $message->content->text);
        self::assertSame($createdAt, $message->createdAt);
    }
}
