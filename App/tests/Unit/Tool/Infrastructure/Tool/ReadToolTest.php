<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\ReadTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReadTool::class)]
final class ReadToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_read_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        file_put_contents(
            $base.'/hello.php',
            "<?php\necho 'a';\necho 'b';\necho 'c';\necho 'd';\necho 'e';\n",
        );
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $files = glob($this->root.'/*') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($this->root);
    }

    public function testReadsFileWithLineNumbers(): void
    {
        $result = $this->runTool(['filePath' => 'hello.php']);

        self::assertFalse($result->isError);
        self::assertStringContainsString('     1  <?php', $result->output);
        self::assertStringContainsString('     6  ', $result->output);
        self::assertSame(6, $result->metadata['lines_returned'] ?? null);
    }

    public function testOffsetSkipsLines(): void
    {
        $result = $this->runTool(['filePath' => 'hello.php', 'offset' => 3]);

        self::assertFalse($result->isError);
        self::assertStringNotContainsString('     1  ', $result->output);
        self::assertStringContainsString("     4  echo 'c';", $result->output);
    }

    public function testLimitCapsLineCount(): void
    {
        $result = $this->runTool(['filePath' => 'hello.php', 'limit' => 2]);

        self::assertFalse($result->isError);
        self::assertSame(2, $result->metadata['lines_returned'] ?? null);
    }

    public function testReturnsErrorForMissingFile(): void
    {
        $result = $this->runTool(['filePath' => 'nope.php']);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->output);
    }

    public function testReturnsErrorForPathOutsideRoot(): void
    {
        $result = $this->runTool(['filePath' => '../../../etc/passwd']);

        self::assertTrue($result->isError);
        // Either "not found" (realpath null) or "outside the project root" — both safe.
        self::assertMatchesRegularExpression('/not found|outside the project root/', $result->output);
    }

    public function testRejectsEmptyPath(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => '']);
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => 'hello.php', 'offset' => -1]);
    }

    public function testTruncatesAtMaxBytes(): void
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_r'), ToolName::of('read'), ['filePath' => 'hello.php']);
        $ctx = new ToolExecutionContext($this->root, 20, new \DateTimeImmutable());

        $result = (new ReadTool())->execute($call, $ctx);

        self::assertFalse($result->isError);
        self::assertStringContainsString('--- truncated at 20 bytes ---', $result->output);
        self::assertTrue($result->metadata['truncated'] ?? false);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args): \App\Tool\Domain\Model\ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_r'), ToolName::of('read'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new ReadTool())->execute($call, $ctx);
    }
}
