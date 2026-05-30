<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model\ValueObject;

use App\Tool\Domain\Model\ValueObject\JsonSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSchema::class)]
final class JsonSchemaTest extends TestCase
{
    public function testAcceptsObjectSchemaWithProperties(): void
    {
        $schema = JsonSchema::of([
            'type' => 'object',
            'properties' => ['pattern' => ['type' => 'string']],
            'required' => ['pattern'],
        ]);

        self::assertSame('object', $schema->value['type']);
        \assert(isset($schema->value['required']));
        self::assertSame(['pattern'], $schema->value['required']);
    }

    public function testEmptyReturnsObjectSchema(): void
    {
        $schema = JsonSchema::empty();

        self::assertSame('object', $schema->value['type']);
    }

    public function testRejectsNonObjectRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonSchema::of(['type' => 'string']);
    }

    public function testRejectsNonArrayProperties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonSchema::of(['type' => 'object', 'properties' => 'oops']);
    }

    public function testRejectsNonListRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonSchema::of([
            'type' => 'object',
            'required' => ['first' => 'pattern'],
        ]);
    }
}
