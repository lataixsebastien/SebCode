<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tests\Support\Tool\Doubles\FakeDiagnosticsProvider;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\Diagnostic;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\DiagnosticSeverity;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\LspTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LspTool::class)]
final class LspToolTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sebcode_lsp_'.bin2hex(random_bytes(4));
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

    public function testReportsProblems(): void
    {
        file_put_contents($this->root.'/a.php', "<?php\n");
        $provider = new FakeDiagnosticsProvider([
            new Diagnostic(DiagnosticSeverity::Error, 12, 'Undefined variable $x', 'phpstan'),
        ]);

        $result = $this->runTool(['filePath' => 'a.php'], $provider);

        self::assertFalse($result->isError);
        self::assertStringContainsString('Found 1 problem(s) in a.php', $result->output);
        self::assertStringContainsString('[error] line 12: Undefined variable $x [phpstan]', $result->output);
        self::assertSame(1, $result->metadata['problems'] ?? null);
        self::assertSame($this->root.'/a.php', $provider->paths[0]);
    }

    public function testReportsNoProblems(): void
    {
        file_put_contents($this->root.'/clean.php', "<?php\n");

        $result = $this->runTool(['filePath' => 'clean.php'], new FakeDiagnosticsProvider());

        self::assertFalse($result->isError);
        self::assertStringContainsString('No problems found in clean.php', $result->output);
    }

    public function testMissingFileIsSoftFailure(): void
    {
        $result = $this->runTool(['filePath' => 'nope.php'], new FakeDiagnosticsProvider());

        self::assertTrue($result->isError);
        self::assertStringContainsString('not found', $result->output);
    }

    public function testRejectsTraversalAsSoftFailure(): void
    {
        $result = $this->runTool(['filePath' => '../../etc/hosts'], new FakeDiagnosticsProvider());

        self::assertTrue($result->isError);
    }

    public function testRejectsEmptyPath(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['filePath' => ''], new FakeDiagnosticsProvider());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args, FakeDiagnosticsProvider $provider): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_l'), ToolName::of('lsp'), $args);
        $ctx = new ToolExecutionContext($this->root, 65536, new \DateTimeImmutable());

        return (new LspTool($provider))->execute($call, $ctx);
    }
}
