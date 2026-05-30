<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model\ValueObject;

use App\Assistant\Domain\Model\ValueObject\MessageContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageContent::class)]
final class MessageContentTest extends TestCase
{
    public function testHoldsText(): void
    {
        $content = MessageContent::of('hello world');

        self::assertSame('hello world', $content->text);
        self::assertSame('hello world', (string) $content);
    }

    public function testIsEmptyForEmptyString(): void
    {
        self::assertTrue(MessageContent::of('')->isEmpty());
    }

    public function testIsEmptyForWhitespace(): void
    {
        self::assertTrue(MessageContent::of("  \n\t  ")->isEmpty());
    }

    public function testIsNotEmptyForRealText(): void
    {
        self::assertFalse(MessageContent::of('x')->isEmpty());
    }
}
