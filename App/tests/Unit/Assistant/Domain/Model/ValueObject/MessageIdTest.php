<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model\ValueObject;

use App\Assistant\Domain\Model\ValueObject\MessageId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageId::class)]
final class MessageIdTest extends TestCase
{
    public function testFromStringHappyPath(): void
    {
        $id = MessageId::fromString('msg_abc123');

        self::assertSame('msg_abc123', $id->value);
    }

    public function testRejectsMissingPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MessageId::fromString('xxx_abc');
    }

    public function testRejectsPrefixOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MessageId::fromString('msg_');
    }

    public function testEquals(): void
    {
        self::assertTrue(MessageId::fromString('msg_1')->equals(MessageId::fromString('msg_1')));
        self::assertFalse(MessageId::fromString('msg_1')->equals(MessageId::fromString('msg_2')));
    }
}
