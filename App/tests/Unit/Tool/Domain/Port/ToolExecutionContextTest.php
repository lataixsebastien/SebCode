<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Port;

use App\Tool\Domain\Port\ToolExecutionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolExecutionContext::class)]
final class ToolExecutionContextTest extends TestCase
{
    public function testHoldsAllFields(): void
    {
        $now = new \DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $ctx = new ToolExecutionContext('/var/www/App', 1024, $now);

        self::assertSame('/var/www/App', $ctx->projectRoot);
        self::assertSame(1024, $ctx->maxOutputBytes);
        self::assertSame($now, $ctx->now);
    }

    public function testRejectsEmptyRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ToolExecutionContext('', 1024, new \DateTimeImmutable());
    }

    public function testRejectsNonPositiveMaxOutput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ToolExecutionContext('/x', 0, new \DateTimeImmutable());
    }
}
