<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\GrepTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrepTool::class)]
final class GrepToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_grep_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testFindsMatchesWithFileAndLineNumber(): void
    {
        $this->write('a.php', "first\nneedle here\nlast\n");

        $result = $this->runTool(['pattern' => 'needle']);

        self::assertFalse($result->isError);
        self::assertStringContainsString('Found 1 matches', $result->output);
        self::assertStringContainsString('a.php:', $result->output);
        self::assertStringContainsString('Line 2: needle here', $result->output);
        self::assertSame(1, $result->metadata['matches'] ?? null);
    }

    public function testNoMatchesReturnsZero(): void
    {
        $this->write('a.php', "nothing to see\n");

        $result = $this->runTool(['pattern' => 'absent']);

        self::assertStringContainsString('Found 0 matches.', $result->output);
        self::assertSame(0, $result->metadata['matches'] ?? null);
    }

    public function testSupportsRegexSyntax(): void
    {
        $this->write('a.php', "function foo()\nclass Bar\n");

        $result = $this->runTool(['pattern' => 'function\s+\w+']);

        self::assertStringContainsString('Line 1: function foo()', $result->output);
        self::assertSame(1, $result->metadata['matches'] ?? null);
    }

    public function testIncludeFilterRestrictsByExtension(): void
    {
        $this->write('keep.php', "needle\n");
        $this->write('skip.txt', "needle\n");

        $result = $this->runTool(['pattern' => 'needle', 'include' => '*.php']);

        self::assertStringContainsString('keep.php', $result->output);
        self::assertStringNotContainsString('skip.txt', $result->output);
    }

    public function testIncludeBraceExpansion(): void
    {
        $this->write('a.ts', "needle\n");
        $this->write('b.tsx', "needle\n");
        $this->write('c.js', "needle\n");

        $result = $this->runTool(['pattern' => 'needle', 'include' => '*.{ts,tsx}']);

        self::assertStringContainsString('a.ts', $result->output);
        self::assertStringContainsString('b.tsx', $result->output);
        self::assertStringNotContainsString('c.js', $result->output);
    }

    public function testPathScopesTheSearch(): void
    {
        $this->write('sub/in.php', "needle\n");
        $this->write('out.php', "needle\n");

        $result = $this->runTool(['pattern' => 'needle', 'path' => 'sub']);

        self::assertStringContainsString('in.php', $result->output);
        self::assertStringNotContainsString('out.php', $result->output);
    }

    public function testRejectsPathOutsideRoot(): void
    {
        $result = $this->runTool(['pattern' => 'x', 'path' => '../..']);

        self::assertTrue($result->isError);
        self::assertStringContainsString('outside the project root', $result->output);
    }

    public function testMissingPathIsSoftFailure(): void
    {
        $result = $this->runTool(['pattern' => 'x', 'path' => 'does/not/exist']);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->output);
    }

    public function testInvalidRegexIsSoftFailure(): void
    {
        $this->write('a.php', "needle\n");

        $result = $this->runTool(['pattern' => '(unclosed']);

        self::assertTrue($result->isError);
        self::assertStringContainsString('invalid regular expression', $result->output);
    }

    public function testSkipsBinaryFiles(): void
    {
        $this->write('blob.bin', "before\0needle after\n");

        $result = $this->runTool(['pattern' => 'needle']);

        self::assertStringContainsString('Found 0 matches.', $result->output);
    }

    public function testExcludesGitDirectory(): void
    {
        $this->write('.git/config', "needle in git\n");

        $result = $this->runTool(['pattern' => 'needle']);

        self::assertStringContainsString('Found 0 matches.', $result->output);
    }

    public function testTruncatesVeryLongLine(): void
    {
        $this->write('a.php', str_repeat('x', 2100)."needle\n");

        $result = $this->runTool(['pattern' => 'needle']);

        self::assertStringContainsString('...', $result->output);
        self::assertStringNotContainsString(str_repeat('x', 2100), $result->output);
    }

    public function testSortsNewestFileFirst(): void
    {
        $this->write('old.php', "needle\n");
        $this->write('new.php', "needle\n");
        touch($this->root.'/old.php', 1_000_000);
        touch($this->root.'/new.php', 2_000_000);

        $result = $this->runTool(['pattern' => 'needle']);

        self::assertLessThan(
            strpos($result->output, 'old.php') ?: \PHP_INT_MAX,
            strpos($result->output, 'new.php') ?: \PHP_INT_MAX,
            'The more recently modified file must be listed first.',
        );
    }

    public function testRejectsEmptyPattern(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['pattern' => '']);
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->root.\DIRECTORY_SEPARATOR.$relative;
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_g'), ToolName::of('grep'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new GrepTool())->execute($call, $ctx);
    }

    private function rmrf(string $dir): void
    {
        $items = glob($dir.'/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->rmrf($item) : @unlink($item);
        }
        // Also remove hidden entries (e.g. .git) created by tests.
        $hidden = glob($dir.'/.[!.]*') ?: [];
        foreach ($hidden as $item) {
            is_dir($item) ? $this->rmrf($item) : @unlink($item);
        }
        @rmdir($dir);
    }
}
