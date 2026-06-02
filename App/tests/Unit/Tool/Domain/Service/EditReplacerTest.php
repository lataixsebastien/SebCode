<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Service;

use App\Tool\Domain\Exception\EditConflict;
use App\Tool\Domain\Service\EditReplacer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditReplacer::class)]
#[CoversClass(EditConflict::class)]
final class EditReplacerTest extends TestCase
{
    private EditReplacer $replacer;

    protected function setUp(): void
    {
        $this->replacer = new EditReplacer();
    }

    public function testExactSingleReplacement(): void
    {
        $content = "line a\nline b\nline c\n";

        self::assertSame(
            "line a\nLINE B\nline c\n",
            $this->replacer->replace($content, 'line b', 'LINE B'),
        );
    }

    public function testReplaceAllReplacesEveryOccurrence(): void
    {
        $content = "x = 1\ny = x\nz = x\n";

        // Faithful to opencode: replaceAll does content.replaceAll(search, …),
        // so every byte-level 'x' is rewritten — including the one in "x = 1".
        self::assertSame(
            "q = 1\ny = q\nz = q\n",
            $this->replacer->replace($content, 'x', 'q', true),
        );
    }

    public function testThrowsOnNoChange(): void
    {
        $this->expectException(EditConflict::class);
        $this->expectExceptionMessage('identical');

        $this->replacer->replace('abc', 'a', 'a');
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->expectException(EditConflict::class);
        $this->expectExceptionMessage('Could not find oldString');

        $this->replacer->replace("hello\nworld\n", 'missing', 'x');
    }

    public function testThrowsOnMultipleAmbiguousMatches(): void
    {
        // "foo" appears twice and is not unique; no replacer yields a unique hit.
        $this->expectException(EditConflict::class);
        $this->expectExceptionMessage('multiple matches');

        $this->replacer->replace("foo\nfoo\n", 'foo', 'bar');
    }

    public function testLineTrimmedToleratesSurroundingIndentation(): void
    {
        // oldString lacks the leading indentation present in the file.
        $content = "function f() {\n        return 1;\n}\n";

        $result = $this->replacer->replace($content, 'return 1;', 'return 2;');

        self::assertStringContainsString('return 2;', $result);
        self::assertStringNotContainsString('return 1;', $result);
        // Original indentation is preserved (the actual line was replaced).
        self::assertStringContainsString('        return 2;', $result);
    }

    public function testBlockAnchorMatchesDespiteMiddleDrift(): void
    {
        $content = "start\n  middle original\nend\nother\n";
        // First and last anchor lines match; the middle differs slightly.
        $old = "start\n  middle changed\nend";
        $new = "start\n  brand new\nend";

        $result = $this->replacer->replace($content, $old, $new);

        self::assertStringContainsString('brand new', $result);
        self::assertStringContainsString('other', $result);
    }
}
