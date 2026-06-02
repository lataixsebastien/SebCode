<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tests\Support\Tool\Doubles\FakeCommandRunner;
use App\Tests\Support\Tool\Doubles\FakePermissionGate;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\CommandResult;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\ShellTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShellTool::class)]
final class ShellToolTest extends TestCase
{
    public function testRunsCommandAndReturnsOutputWithExitCode(): void
    {
        $runner = new FakeCommandRunner(new CommandResult("hello\n", '', 0));
        $result = $this->runTool(['command' => 'echo hello'], runner: $runner);

        self::assertFalse($result->isError);
        self::assertStringContainsString('hello', $result->output);
        self::assertStringContainsString('[exit code: 0]', $result->output);
        self::assertSame(0, $result->metadata['exitCode'] ?? null);
        self::assertSame('echo hello', $runner->calls[0]['command']);
        self::assertSame(120000, $runner->calls[0]['timeoutMs'], 'Default timeout is 120s.');
    }

    public function testNonZeroExitIsSoftError(): void
    {
        $runner = new FakeCommandRunner(new CommandResult('', 'boom', 2));
        $result = $this->runTool(['command' => 'false'], runner: $runner);

        self::assertTrue($result->isError);
        self::assertStringContainsString('[exit code: 2]', $result->output);
    }

    public function testStderrIsAppendedToOutput(): void
    {
        $runner = new FakeCommandRunner(new CommandResult("out\n", "err\n", 0));
        $result = $this->runTool(['command' => 'cmd'], runner: $runner);

        self::assertStringContainsString('out', $result->output);
        self::assertStringContainsString('err', $result->output);
    }

    public function testTimeoutIsSoftErrorWithMessage(): void
    {
        $runner = new FakeCommandRunner(new CommandResult('partial', '', null, timedOut: true));
        $result = $this->runTool(['command' => 'sleep 999'], runner: $runner);

        self::assertTrue($result->isError);
        self::assertStringContainsString('timeout', $result->output);
        self::assertTrue($result->metadata['timedOut'] ?? false);
    }

    public function testAsksBashPermissionWithCommandAndAlwaysPattern(): void
    {
        $gate = new FakePermissionGate();
        $this->runTool(['command' => 'git status'], gate: $gate);

        self::assertCount(1, $gate->requests);
        self::assertSame(PermissionType::Bash, $gate->requests[0]->type);
        self::assertSame(['git status'], $gate->requests[0]->patterns);
        self::assertSame(['git *'], $gate->requests[0]->always);
    }

    public function testDeniedPermissionThrowsAndNeverRuns(): void
    {
        $runner = new FakeCommandRunner(new CommandResult('', '', 0));
        $gate = new FakePermissionGate(allow: false);

        try {
            $this->runTool(['command' => 'rm -rf /'], gate: $gate, runner: $runner);
            self::fail('expected PermissionDenied');
        } catch (PermissionDenied) {
            self::assertSame([], $runner->calls, 'A denied command must not reach the runner.');
        }
    }

    public function testCustomTimeoutIsForwardedAndCapped(): void
    {
        $runner = new FakeCommandRunner(new CommandResult('', '', 0));
        $this->runTool(['command' => 'x', 'timeout' => 5000], runner: $runner);
        $this->runTool(['command' => 'x', 'timeout' => 5_000_000], runner: $runner);

        self::assertSame(5000, $runner->calls[0]['timeoutMs']);
        self::assertSame(600000, $runner->calls[1]['timeoutMs'], 'Timeout is capped at 600s.');
    }

    public function testRejectsEmptyCommand(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['command' => '   ']);
    }

    public function testRejectsNonPositiveTimeout(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['command' => 'x', 'timeout' => 0]);
    }

    public function testTruncatesLongOutput(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(str_repeat('A', 200), '', 0));
        $ctx = new ToolExecutionContext(sys_get_temp_dir(), 50, new \DateTimeImmutable());
        $call = new ToolCall(ToolCallId::fromString('tcl_b'), ToolName::of('bash'), ['command' => 'x']);

        $result = (new ShellTool(new FakePermissionGate(), $runner))->execute($call, $ctx);

        self::assertStringContainsString('...output truncated...', $result->output);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args, ?FakePermissionGate $gate = null, ?FakeCommandRunner $runner = null): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_b'), ToolName::of('bash'), $args);
        $ctx = new ToolExecutionContext(sys_get_temp_dir(), 65536, new \DateTimeImmutable());
        $runner ??= new FakeCommandRunner(new CommandResult('', '', 0));

        return (new ShellTool($gate ?? new FakePermissionGate(), $runner))->execute($call, $ctx);
    }
}
