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
use App\Tool\Domain\Service\PatchApplier;
use App\Tool\Domain\Service\PatchParser;
use App\Tool\Infrastructure\Tool\ApplyPatchTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplyPatchTool::class)]
final class ApplyPatchToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_patch_'.bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $this->root = (string) realpath($base);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testAddsFile(): void
    {
        $result = $this->runTool("*** Begin Patch\n*** Add File: sub/new.txt\n+hello\n*** End Patch");

        self::assertFalse($result->isError);
        self::assertStringContainsString('A sub/new.txt', $result->output);
        self::assertSame("hello\n", file_get_contents($this->root.'/sub/new.txt'));
    }

    public function testUpdatesFile(): void
    {
        file_put_contents($this->root.'/app.php', "a\nold\nc\n");

        $result = $this->runTool("*** Begin Patch\n*** Update File: app.php\n@@\n a\n-old\n+new\n c\n*** End Patch");

        self::assertFalse($result->isError);
        self::assertStringContainsString('M app.php', $result->output);
        self::assertSame("a\nnew\nc\n", file_get_contents($this->root.'/app.php'));
    }

    public function testDeletesFile(): void
    {
        file_put_contents($this->root.'/gone.txt', 'x');

        $result = $this->runTool("*** Begin Patch\n*** Delete File: gone.txt\n*** End Patch");

        self::assertFalse($result->isError);
        self::assertStringContainsString('D gone.txt', $result->output);
        self::assertFileDoesNotExist($this->root.'/gone.txt');
    }

    public function testMovesFile(): void
    {
        file_put_contents($this->root.'/from.php', "x\n");

        $result = $this->runTool(
            "*** Begin Patch\n*** Update File: from.php\n*** Move to: to.php\n@@\n-x\n+y\n*** End Patch",
        );

        self::assertFalse($result->isError);
        self::assertStringContainsString('M from.php -> to.php', $result->output);
        self::assertFileDoesNotExist($this->root.'/from.php');
        self::assertSame("y\n", file_get_contents($this->root.'/to.php'));
    }

    public function testAllOrNothingWhenAnOperationFails(): void
    {
        // First op is a valid Add, second targets a missing file → nothing written.
        $result = $this->runTool(
            "*** Begin Patch\n*** Add File: a.txt\n+a\n*** Update File: missing.php\n@@\n-x\n+y\n*** End Patch",
        );

        self::assertTrue($result->isError);
        self::assertFileDoesNotExist($this->root.'/a.txt');
    }

    public function testParseErrorIsSoftFailure(): void
    {
        $result = $this->runTool('not a patch at all');

        self::assertTrue($result->isError);
        self::assertStringContainsString('Invalid patch', $result->output);
    }

    public function testRejectsTraversalAsSoftFailure(): void
    {
        $result = $this->runTool("*** Begin Patch\n*** Add File: ../escape.txt\n+x\n*** End Patch");

        self::assertTrue($result->isError);
        self::assertFileDoesNotExist(\dirname($this->root).'/escape.txt');
    }

    public function testAsksEditPermissionWithAllPaths(): void
    {
        $gate = new FakePermissionGate();
        $this->runTool("*** Begin Patch\n*** Add File: one.txt\n+1\n*** Add File: two.txt\n+2\n*** End Patch", $gate);

        self::assertCount(1, $gate->requests, 'One permission ask for the whole patch.');
        self::assertSame(PermissionType::Edit, $gate->requests[0]->type);
        self::assertSame(['one.txt', 'two.txt'], $gate->requests[0]->patterns);
    }

    public function testDeniedPermissionWritesNothing(): void
    {
        $gate = new FakePermissionGate(allow: false);

        try {
            $this->runTool("*** Begin Patch\n*** Add File: denied.txt\n+x\n*** End Patch", $gate);
            self::fail('expected PermissionDenied');
        } catch (PermissionDenied) {
            self::assertFileDoesNotExist($this->root.'/denied.txt');
        }
    }

    public function testRejectsEmptyPatchText(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool('   ');
    }

    private function runTool(string $patchText, ?FakePermissionGate $gate = null): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_p'), ToolName::of('apply_patch'), ['patchText' => $patchText]);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());
        $tool = new ApplyPatchTool($gate ?? new FakePermissionGate(), new PatchParser(), new PatchApplier());

        return $tool->execute($call, $ctx);
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
