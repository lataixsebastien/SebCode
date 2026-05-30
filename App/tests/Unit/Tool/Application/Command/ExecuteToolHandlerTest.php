<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Application\Command;

use App\Tests\Support\Tool\Doubles\FakeTool;
use App\Tests\Support\Tool\Doubles\InMemoryToolRegistry;
use App\Tool\Application\Command\ExecuteToolCommand;
use App\Tool\Application\Command\ExecuteToolHandler;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\ToolNotFound;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecuteToolHandler::class)]
#[CoversClass(ExecuteToolCommand::class)]
final class ExecuteToolHandlerTest extends TestCase
{
    public function testExecutesRegisteredToolAndReturnsItsResult(): void
    {
        $registry = new InMemoryToolRegistry();
        $tool = new FakeTool('fake');
        $tool->scriptOutput('done');
        $registry->register($tool);

        $call = new ToolCall(ToolCallId::fromString('tcl_1'), ToolName::of('fake'), ['x' => 1]);
        $ctx = $this->ctx();
        $result = (new ExecuteToolHandler($registry))(new ExecuteToolCommand($call, $ctx));

        self::assertSame('done', $result->output);
        self::assertFalse($result->isError);
        self::assertCount(1, $tool->calls);
        self::assertSame($call, $tool->calls[0]['call']);
        self::assertSame($ctx, $tool->calls[0]['context']);
    }

    public function testThrowsToolNotFoundForUnknownTool(): void
    {
        $registry = new InMemoryToolRegistry();
        $call = new ToolCall(ToolCallId::fromString('tcl_1'), ToolName::of('ghost'));

        $this->expectException(ToolNotFound::class);
        (new ExecuteToolHandler($registry))(new ExecuteToolCommand($call, $this->ctx()));
    }

    public function testConvertsInvalidArgumentsIntoErrorResult(): void
    {
        $registry = new InMemoryToolRegistry();
        $tool = new FakeTool('fake');
        $tool->scriptException(InvalidToolArguments::for(ToolName::of('fake'), 'missing pattern'));
        $registry->register($tool);

        $call = new ToolCall(ToolCallId::fromString('tcl_1'), ToolName::of('fake'));
        $result = (new ExecuteToolHandler($registry))(new ExecuteToolCommand($call, $this->ctx()));

        self::assertTrue($result->isError);
        self::assertStringContainsString('missing pattern', $result->output);
    }

    public function testHardFailureBubblesUp(): void
    {
        $registry = new InMemoryToolRegistry();
        $tool = new FakeTool('fake');
        $tool->scriptException(new \RuntimeException('infra broken'));
        $registry->register($tool);

        $call = new ToolCall(ToolCallId::fromString('tcl_1'), ToolName::of('fake'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/infra broken/');
        (new ExecuteToolHandler($registry))(new ExecuteToolCommand($call, $this->ctx()));
    }

    private function ctx(): ToolExecutionContext
    {
        return new ToolExecutionContext('/tmp/project', 65536, new \DateTimeImmutable('2026-05-30T10:00:00+00:00'));
    }
}
