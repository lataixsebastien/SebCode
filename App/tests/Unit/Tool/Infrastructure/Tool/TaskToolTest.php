<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tests\Support\Tool\Doubles\FakeSubAgentRunner;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Tool\TaskTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskTool::class)]
final class TaskToolTest extends TestCase
{
    public function testDelegatesPromptAndReturnsTheAnswer(): void
    {
        $runner = new FakeSubAgentRunner('found it in Kernel.php');
        $result = $this->runTool(['description' => 'find kernel', 'prompt' => 'where is the kernel?'], $runner);

        self::assertFalse($result->isError);
        self::assertSame('found it in Kernel.php', $result->output);
        self::assertSame('where is the kernel?', $runner->calls[0]['instruction']);
        self::assertSame('ses_test', $runner->calls[0]['sessionId'], 'The parent session scopes the sub-agent.');
    }

    public function testEmptyAnswerIsReportedNotBlank(): void
    {
        $result = $this->runTool(['description' => 'x', 'prompt' => 'y'], new FakeSubAgentRunner('   '));

        self::assertStringContainsString('no answer', $result->output);
    }

    public function testRejectsMissingDescription(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['prompt' => 'y'], new FakeSubAgentRunner());
    }

    public function testRejectsEmptyPrompt(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['description' => 'x', 'prompt' => '  '], new FakeSubAgentRunner());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args, FakeSubAgentRunner $runner): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_k'), ToolName::of('task'), $args);
        $ctx = new ToolExecutionContext(sys_get_temp_dir(), 65536, new \DateTimeImmutable(), 'ses_test');

        return (new TaskTool($runner))->execute($call, $ctx);
    }
}
