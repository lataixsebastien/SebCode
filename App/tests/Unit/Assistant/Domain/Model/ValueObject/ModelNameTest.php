<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Domain\Model\ValueObject;

use App\Assistant\Domain\Model\ValueObject\ModelName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelName::class)]
final class ModelNameTest extends TestCase
{
    public function testOfHappyPath(): void
    {
        $model = ModelName::of('qwen2.5:3b');

        self::assertSame('qwen2.5:3b', $model->value);
        self::assertSame('qwen2.5:3b', (string) $model);
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ModelName::of('');
    }

    public function testRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ModelName::of("   \t  ");
    }

    public function testEquals(): void
    {
        self::assertTrue(ModelName::of('a')->equals(ModelName::of('a')));
        self::assertFalse(ModelName::of('a')->equals(ModelName::of('b')));
    }
}
