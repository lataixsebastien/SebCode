<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model\ValueObject;

use App\Tool\Domain\Model\ValueObject\ToolCallId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolCallId::class)]
final class ToolCallIdTest extends TestCase
{
    public function testFromStringHappyPath(): void
    {
        $id = ToolCallId::fromString('tcl_abc123');

        self::assertSame('tcl_abc123', $id->value);
        self::assertSame('tcl_abc123', (string) $id);
    }

    public function testRejectsMissingPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ToolCallId::fromString('abc123');
    }

    public function testRejectsPrefixOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ToolCallId::fromString('tcl_');
    }

    public function testEquals(): void
    {
        self::assertTrue(ToolCallId::fromString('tcl_1')->equals(ToolCallId::fromString('tcl_1')));
        self::assertFalse(ToolCallId::fromString('tcl_1')->equals(ToolCallId::fromString('tcl_2')));
    }
}
