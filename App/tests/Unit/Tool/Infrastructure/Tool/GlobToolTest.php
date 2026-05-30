<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\GlobTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobTool::class)]
final class GlobToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_glob_'.bin2hex(random_bytes(4));
        mkdir($base.'/src/Foo', 0o777, true);
        mkdir($base.'/src/Bar/Deep', 0o777, true);
        file_put_contents($base.'/src/Foo/a.php', '<?php');
        file_put_contents($base.'/src/Foo/b.php', '<?php');
        file_put_contents($base.'/src/Bar/c.php', '<?php');
        file_put_contents($base.'/src/Bar/Deep/d.php', '<?php');
        file_put_contents($base.'/readme.md', '# hi');
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testMatchesShallowGlob(): void
    {
        $result = $this->runTool(['pattern' => 'src/Foo/*.php']);

        self::assertFalse($result->isError);
        self::assertStringContainsString('(2 matched, showing first 2)', $result->output);
        self::assertStringContainsString('src/Foo/a.php', $this->normalize($result->output));
        self::assertStringContainsString('src/Foo/b.php', $this->normalize($result->output));
    }

    public function testMatchesRecursiveDoubleStar(): void
    {
        $result = $this->runTool(['pattern' => 'src/**/*.php']);

        self::assertFalse($result->isError);
        $normalized = $this->normalize($result->output);
        self::assertStringContainsString('src/Foo/a.php', $normalized);
        self::assertStringContainsString('src/Bar/Deep/d.php', $normalized);
    }

    public function testTruncatesBeyondLimitWithMarker(): void
    {
        $result = $this->runTool(['pattern' => 'src/**/*.php', 'limit' => 2]);

        self::assertFalse($result->isError);
        self::assertStringContainsString('(4 matched, showing first 2)', $result->output);
        self::assertStringContainsString('--- truncated ---', $result->output);
    }

    public function testReturnsZeroMatchedForNoHit(): void
    {
        $result = $this->runTool(['pattern' => 'src/**/*.go']);

        self::assertFalse($result->isError);
        self::assertStringContainsString('(0 matched, showing first 0)', $result->output);
    }

    public function testRejectsEmptyPattern(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['pattern' => '']);
    }

    public function testRejectsNonStringPattern(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['pattern' => 42]);
    }

    public function testRejectsLimitOutOfRange(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['pattern' => 'src/Foo/*.php', 'limit' => 9999]);
    }

    public function testSandboxIgnoresParentTraversal(): void
    {
        $result = $this->runTool(['pattern' => '../*']);

        self::assertFalse($result->isError);
        // Any path that ends up returned MUST resolve inside the project root.
        // Lexical "../foo" entries from the upper directory are filtered out;
        // a hop like "../<self>" that loops back to root is allowed since it
        // still points inside the sandbox.
        $lines = \array_slice(explode("\n", trim($result->output)), 1);
        foreach ($lines as $line) {
            if ('' === $line) {
                continue;
            }
            $resolved = realpath($line);
            self::assertNotFalse($resolved);
            self::assertTrue(
                $resolved === $this->root
                    || str_starts_with($resolved, $this->root.\DIRECTORY_SEPARATOR),
                \sprintf('Returned path %s escapes the sandbox %s', $resolved, $this->root),
            );
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args): \App\Tool\Domain\Model\ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_glob1'), ToolName::of('glob'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new GlobTool())->execute($call, $ctx);
    }

    private function normalize(string $s): string
    {
        return str_replace('\\', '/', $s);
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $this->rmrf($path.\DIRECTORY_SEPARATOR.$entry);
        }
        @rmdir($path);
    }
}
