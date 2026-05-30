<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Model\ValueObject;

use App\Tool\Domain\Model\ValueObject\ToolName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolName::class)]
final class ToolNameTest extends TestCase
{
    #[DataProvider('validNames')]
    public function testAcceptsValidNames(string $name): void
    {
        $vo = ToolName::of($name);

        self::assertSame($name, $vo->value);
        self::assertSame($name, (string) $vo);
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ToolName::of($name);
    }

    public function testEquals(): void
    {
        self::assertTrue(ToolName::of('glob')->equals(ToolName::of('glob')));
        self::assertFalse(ToolName::of('glob')->equals(ToolName::of('read')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNames(): iterable
    {
        yield 'short' => ['a'];
        yield 'underscore start' => ['_internal'];
        yield 'with digits' => ['glob2'];
        yield 'snake case' => ['repo_overview'];
        yield 'long but valid' => [str_repeat('a', 64)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Glob'];
        yield 'dash' => ['glob-tool'];
        yield 'dot' => ['glob.read'];
        yield 'starts with digit' => ['2glob'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'whitespace' => ['glob read'];
    }
}
