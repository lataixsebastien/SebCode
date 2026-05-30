<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model;

use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolCall::class)]
final class ToolCallTest extends TestCase
{
    public function testHoldsAllFields(): void
    {
        $call = new ToolCall(
            ToolCallId::fromString('tcl_42'),
            ToolName::of('glob'),
            ['pattern' => '*.php', 'limit' => 50],
        );

        self::assertSame('tcl_42', $call->id->value);
        self::assertSame('glob', $call->name->value);
        self::assertSame(['pattern' => '*.php', 'limit' => 50], $call->arguments);
    }

    public function testArgumentsDefaultToEmpty(): void
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_x'), ToolName::of('list'));

        self::assertSame([], $call->arguments);
    }
}
