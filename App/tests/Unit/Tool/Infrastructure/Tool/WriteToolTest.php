<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tests\Support\Tool\Doubles\FakePermissionGate;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\WriteTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WriteTool::class)]
final class WriteToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_write_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testCreatesNewFile(): void
    {
        $result = $this->runTool(['filePath' => 'new.txt', 'content' => "hello\n"]);

        self::assertFalse($result->isError);
        self::assertFileExists($this->root.'/new.txt');
        self::assertSame("hello\n", file_get_contents($this->root.'/new.txt'));
        self::assertTrue($result->metadata['created'] ?? null);
    }

    public function testOverwritesExistingFile(): void
    {
        file_put_contents($this->root.'/x.txt', 'old');

        $result = $this->runTool(['filePath' => 'x.txt', 'content' => 'new']);

        self::assertFalse($result->isError);
        self::assertSame('new', file_get_contents($this->root.'/x.txt'));
        self::assertFalse($result->metadata['created'] ?? null);
    }

    public function testCreatesNestedDirectories(): void
    {
        $result = $this->runTool(['filePath' => 'a/b/c.txt', 'content' => 'deep']);

        self::assertFalse($result->isError);
        self::assertSame('deep', file_get_contents($this->root.'/a/b/c.txt'));
    }

    public function testAsksPermissionWithEditTypeAndRelativePattern(): void
    {
        $gate = new FakePermissionGate();
        $this->runTool(['filePath' => 'sub/f.txt', 'content' => 'x'], $gate);

        self::assertCount(1, $gate->requests);
        self::assertSame(PermissionType::Edit, $gate->requests[0]->type);
        self::assertSame(['sub/f.txt'], $gate->requests[0]->patterns);
    }

    public function testDeniedPermissionThrowsAndWritesNothing(): void
    {
        $gate = new FakePermissionGate(allow: false);

        try {
            $this->runTool(['filePath' => 'denied.txt', 'content' => 'x'], $gate);
            self::fail('expected PermissionDenied');
        } catch (PermissionDenied) {
            self::assertFileDoesNotExist($this->root.'/denied.txt');
        }
    }

    public function testRejectsTraversalAsSoftFailure(): void
    {
        $result = $this->runTool(['filePath' => '../escape.txt', 'content' => 'x']);

        self::assertTrue($result->isError);
        self::assertStringContainsString('..', $result->output);
    }

    public function testRejectsEmptyPath(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => '', 'content' => 'x']);
    }

    public function testRejectsNonStringContent(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => 'f.txt', 'content' => 123]);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args, ?FakePermissionGate $gate = null): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_w'), ToolName::of('write'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new WriteTool($gate ?? new FakePermissionGate()))->execute($call, $ctx);
    }

    private function rmrf(string $dir): void
    {
        $items = glob($dir.'/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->rmrf($item) : @unlink($item);
        }
        @rmdir($dir);
    }
}
