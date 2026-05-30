<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model;

use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolDescriptor::class)]
final class ToolDescriptorTest extends TestCase
{
    public function testHoldsAllFields(): void
    {
        $schema = JsonSchema::empty();
        $descriptor = new ToolDescriptor(
            ToolName::of('glob'),
            'Find files matching a glob pattern.',
            $schema,
        );

        self::assertSame('glob', $descriptor->name->value);
        self::assertSame('Find files matching a glob pattern.', $descriptor->description);
        self::assertSame($schema, $descriptor->parameters);
    }

    public function testRejectsEmptyDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ToolDescriptor(ToolName::of('x'), '   ', JsonSchema::empty());
    }
}
