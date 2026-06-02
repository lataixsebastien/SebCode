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
use App\Tool\Domain\Service\EditReplacer;
use App\Tool\Infrastructure\Tool\EditTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditTool::class)]
final class EditToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_edit_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $items = glob($this->root.'/*') ?: [];
        foreach ($items as $item) {
            @unlink($item);
        }
        @rmdir($this->root);
    }

    public function testAppliesExactEdit(): void
    {
        file_put_contents($this->root.'/f.php', "<?php\necho 'a';\n");

        $result = $this->runTool([
            'filePath' => 'f.php',
            'oldString' => "echo 'a';",
            'newString' => "echo 'b';",
        ]);

        self::assertFalse($result->isError);
        self::assertSame("<?php\necho 'b';\n", file_get_contents($this->root.'/f.php'));
    }

    public function testReplaceAll(): void
    {
        file_put_contents($this->root.'/f.txt', "a\na\na\n");

        $result = $this->runTool([
            'filePath' => 'f.txt',
            'oldString' => 'a',
            'newString' => 'b',
            'replaceAll' => true,
        ]);

        self::assertFalse($result->isError);
        self::assertSame("b\nb\nb\n", file_get_contents($this->root.'/f.txt'));
    }

    public function testEmptyOldStringCreatesFile(): void
    {
        $result = $this->runTool([
            'filePath' => 'created.txt',
            'oldString' => '',
            'newString' => "fresh\n",
        ]);

        self::assertFalse($result->isError);
        self::assertSame("fresh\n", file_get_contents($this->root.'/created.txt'));
    }

    public function testReturnsErrorWhenFileMissing(): void
    {
        $result = $this->runTool([
            'filePath' => 'nope.txt',
            'oldString' => 'x',
            'newString' => 'y',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->output);
    }

    public function testReturnsErrorWhenOldStringAbsent(): void
    {
        file_put_contents($this->root.'/f.txt', "hello\n");

        $result = $this->runTool([
            'filePath' => 'f.txt',
            'oldString' => 'goodbye',
            'newString' => 'x',
        ]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('Could not find oldString', $result->output);
    }

    public function testAsksPermissionBeforeWriting(): void
    {
        file_put_contents($this->root.'/f.txt', 'abc');
        $gate = new FakePermissionGate();

        $this->runTool(['filePath' => 'f.txt', 'oldString' => 'abc', 'newString' => 'xyz'], $gate);

        self::assertCount(1, $gate->requests);
        self::assertSame(PermissionType::Edit, $gate->requests[0]->type);
        self::assertSame(['f.txt'], $gate->requests[0]->patterns);
    }

    public function testDeniedPermissionThrowsAndLeavesFileUntouched(): void
    {
        file_put_contents($this->root.'/f.txt', 'abc');
        $gate = new FakePermissionGate(allow: false);

        try {
            $this->runTool(['filePath' => 'f.txt', 'oldString' => 'abc', 'newString' => 'xyz'], $gate);
            self::fail('expected PermissionDenied');
        } catch (PermissionDenied) {
            self::assertSame('abc', file_get_contents($this->root.'/f.txt'));
        }
    }

    public function testNoMatchDoesNotAskPermission(): void
    {
        file_put_contents($this->root.'/f.txt', 'abc');
        $gate = new FakePermissionGate();

        $result = $this->runTool(['filePath' => 'f.txt', 'oldString' => 'zzz', 'newString' => 'q'], $gate);

        self::assertTrue($result->isError);
        self::assertSame([], $gate->requests, 'permission is only asked once an edit actually applies');
    }

    public function testRejectsNonBooleanReplaceAll(): void
    {
        file_put_contents($this->root.'/f.txt', 'abc');

        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => 'f.txt', 'oldString' => 'a', 'newString' => 'b', 'replaceAll' => 'yes']);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args, ?FakePermissionGate $gate = null): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_e'), ToolName::of('edit'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new EditTool($gate ?? new FakePermissionGate(), new EditReplacer()))->execute($call, $ctx);
    }
}
