<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Domain\Service;

use App\Tool\Domain\Exception\InvalidPatch;
use App\Tool\Domain\Model\ValueObject\PatchOperationKind;
use App\Tool\Domain\Service\PatchParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchParser::class)]
final class PatchParserTest extends TestCase
{
    private PatchParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PatchParser();
    }

    public function testParsesAddFile(): void
    {
        $patches = $this->parser->parse(
            "*** Begin Patch\n*** Add File: hello.txt\n+line one\n+line two\n*** End Patch",
        );

        self::assertCount(1, $patches);
        self::assertSame(PatchOperationKind::Add, $patches[0]->kind);
        self::assertSame('hello.txt', $patches[0]->path);
        self::assertSame("line one\nline two", $patches[0]->content);
    }

    public function testParsesDeleteFile(): void
    {
        $patches = $this->parser->parse("*** Begin Patch\n*** Delete File: gone.txt\n*** End Patch");

        self::assertSame(PatchOperationKind::Delete, $patches[0]->kind);
        self::assertSame('gone.txt', $patches[0]->path);
    }

    public function testParsesUpdateWithHunk(): void
    {
        $patches = $this->parser->parse(
            "*** Begin Patch\n*** Update File: src/app.php\n@@\n keep\n-old\n+new\n*** End Patch",
        );

        self::assertSame(PatchOperationKind::Update, $patches[0]->kind);
        self::assertNull($patches[0]->movePath);
        self::assertCount(1, $patches[0]->hunks);
        self::assertSame(['keep', 'old'], $patches[0]->hunks[0]->oldLines);
        self::assertSame(['keep', 'new'], $patches[0]->hunks[0]->newLines);
    }

    public function testParsesUpdateWithMove(): void
    {
        $patches = $this->parser->parse(
            "*** Begin Patch\n*** Update File: a.php\n*** Move to: b.php\n@@\n-x\n+y\n*** End Patch",
        );

        self::assertSame('a.php', $patches[0]->path);
        self::assertSame('b.php', $patches[0]->movePath);
    }

    public function testParsesMultipleFiles(): void
    {
        $patches = $this->parser->parse(
            "*** Begin Patch\n*** Add File: a.txt\n+a\n*** Delete File: b.txt\n*** End Patch",
        );

        self::assertCount(2, $patches);
        self::assertSame(PatchOperationKind::Add, $patches[0]->kind);
        self::assertSame(PatchOperationKind::Delete, $patches[1]->kind);
    }

    public function testStripsHeredocWrapper(): void
    {
        $patches = $this->parser->parse(
            "cat <<'EOF'\n*** Begin Patch\n*** Add File: x.txt\n+hi\n*** End Patch\nEOF",
        );

        self::assertSame('x.txt', $patches[0]->path);
        self::assertSame('hi', $patches[0]->content);
    }

    public function testThrowsOnMissingMarkers(): void
    {
        $this->expectException(InvalidPatch::class);
        $this->parser->parse("*** Add File: x\n+y");
    }

    public function testThrowsOnUnknownHeader(): void
    {
        $this->expectException(InvalidPatch::class);
        $this->parser->parse("*** Begin Patch\ngarbage line\n*** End Patch");
    }
}
