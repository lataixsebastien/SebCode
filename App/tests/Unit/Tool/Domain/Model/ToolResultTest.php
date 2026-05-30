<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model;

use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolResult::class)]
final class ToolResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = ToolResult::success(ToolCallId::fromString('tcl_1'), 'hello', ['bytes' => 5]);

        self::assertFalse($result->isError);
        self::assertSame('hello', $result->output);
        self::assertSame(['bytes' => 5], $result->metadata);
    }

    public function testFailureFactory(): void
    {
        $result = ToolResult::failure(ToolCallId::fromString('tcl_1'), 'file not found');

        self::assertTrue($result->isError);
        self::assertSame('file not found', $result->output);
        self::assertNull($result->metadata);
    }

    public function testDirectConstructorAllowsNullMetadata(): void
    {
        $result = new ToolResult(ToolCallId::fromString('tcl_2'), 'ok');

        self::assertFalse($result->isError);
        self::assertNull($result->metadata);
    }
}
