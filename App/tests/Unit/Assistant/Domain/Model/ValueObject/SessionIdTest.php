<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model\ValueObject;

use App\Assistant\Domain\Model\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionId::class)]
final class SessionIdTest extends TestCase
{
    public function testFromStringHappyPath(): void
    {
        $id = SessionId::fromString('ses_abc123');

        self::assertSame('ses_abc123', $id->value);
        self::assertSame('ses_abc123', (string) $id);
    }

    public function testRejectsMissingPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SessionId::fromString('abc123');
    }

    public function testRejectsPrefixOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SessionId::fromString('ses_');
    }

    public function testEqualsTrueWhenSameValue(): void
    {
        $a = SessionId::fromString('ses_x');
        $b = SessionId::fromString('ses_x');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsFalseWhenDifferentValue(): void
    {
        $a = SessionId::fromString('ses_x');
        $b = SessionId::fromString('ses_y');

        self::assertFalse($a->equals($b));
    }
}
