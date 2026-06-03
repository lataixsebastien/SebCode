<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Service;

use App\Tool\Domain\Exception\PatchApplyFailed;
use App\Tool\Domain\Model\PatchHunk;
use App\Tool\Domain\Service\PatchApplier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchApplier::class)]
final class PatchApplierTest extends TestCase
{
    private PatchApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new PatchApplier();
    }

    public function testReplacesAContextualHunk(): void
    {
        $out = $this->applier->apply(
            "a\nb\nc",
            [new PatchHunk(['a', 'b'], ['a', 'B'])],
            'f',
        );

        self::assertSame("a\nB\nc", $out);
    }

    public function testInsertsWithEmptyOldLines(): void
    {
        $out = $this->applier->apply("a\nc", [new PatchHunk([], ['NEW'])], 'f');

        self::assertSame("NEW\na\nc", $out);
    }

    public function testMatchesFuzzilyIgnoringTrailingWhitespace(): void
    {
        $out = $this->applier->apply(
            "x\ntarget   \ny",
            [new PatchHunk(['target'], ['done'])],
            'f',
        );

        self::assertSame("x\ndone\ny", $out);
    }

    public function testEndOfFileAnchorMatchesTail(): void
    {
        $out = $this->applier->apply(
            "dup\nmid\ndup",
            [new PatchHunk(['dup'], ['LAST'], isEndOfFile: true)],
            'f',
        );

        self::assertSame("dup\nmid\nLAST", $out);
    }

    public function testThrowsWhenContextIsAbsent(): void
    {
        $this->expectException(PatchApplyFailed::class);
        $this->applier->apply("a\nb", [new PatchHunk(['nope'], ['x'])], 'f');
    }

    public function testAppliesMultipleHunksInOrder(): void
    {
        $out = $this->applier->apply(
            "1\n2\n3\n4",
            [new PatchHunk(['1'], ['ONE']), new PatchHunk(['3'], ['THREE'])],
            'f',
        );

        self::assertSame("ONE\n2\nTHREE\n4", $out);
    }
}
