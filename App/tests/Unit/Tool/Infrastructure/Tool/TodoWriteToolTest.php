<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Infrastructure\Todo\InMemoryTodoStore;
use App\Tool\Infrastructure\Tool\TodoWriteTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TodoWriteTool::class)]
final class TodoWriteToolTest extends TestCase
{
    private InMemoryTodoStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryTodoStore();
    }

    public function testWritesAndPersistsTheList(): void
    {
        $result = $this->runTool(['todos' => [
            ['content' => 'read the file', 'status' => 'completed', 'priority' => 'high'],
            ['content' => 'wire the CLI', 'status' => 'in_progress', 'priority' => 'medium'],
            ['content' => 'add tests', 'status' => 'pending', 'priority' => 'low'],
        ]]);

        self::assertFalse($result->isError);
        self::assertStringContainsString('3 todos · 2 open', $result->output);
        self::assertStringContainsString('[✓] read the file', $result->output);
        self::assertStringContainsString('[•] wire the CLI', $result->output);
        self::assertStringContainsString('[ ] add tests', $result->output);
        self::assertCount(3, $this->store->all());

        $todos = $result->metadata['todos'] ?? null;
        self::assertIsArray($todos);
        $first = $todos[0];
        self::assertIsArray($first);
        self::assertSame('read the file', $first['content']);
        self::assertSame('completed', $first['status']);
    }

    public function testEmptyListClearsTheStore(): void
    {
        $this->runTool(['todos' => [['content' => 'x', 'status' => 'pending', 'priority' => 'low']]]);

        $result = $this->runTool(['todos' => []]);

        self::assertFalse($result->isError);
        self::assertStringContainsString('0 todos', $result->output);
        self::assertSame([], $this->store->all());
    }

    public function testInvalidStatusIsSoftFailure(): void
    {
        $result = $this->runTool(['todos' => [
            ['content' => 'x', 'status' => 'bogus', 'priority' => 'low'],
        ]]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('status', $result->output);
        self::assertSame([], $this->store->all(), 'A rejected write must not mutate the store.');
    }

    public function testInvalidPriorityIsSoftFailure(): void
    {
        $result = $this->runTool(['todos' => [
            ['content' => 'x', 'status' => 'pending', 'priority' => 'urgent'],
        ]]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('priority', $result->output);
    }

    public function testEmptyContentIsSoftFailure(): void
    {
        $result = $this->runTool(['todos' => [
            ['content' => '  ', 'status' => 'pending', 'priority' => 'low'],
        ]]);

        self::assertTrue($result->isError);
        self::assertStringContainsString('content', $result->output);
    }

    public function testNonArrayTodosThrows(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool(['todos' => 'nope']);
    }

    public function testMissingTodosThrows(): void
    {
        $this->expectException(InvalidToolArguments::class);
        $this->runTool([]);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runTool(array $args): ToolResult
    {
        $call = new ToolCall(ToolCallId::fromString('tcl_t'), ToolName::of('todowrite'), $args);
        $ctx = new ToolExecutionContext(sys_get_temp_dir(), 65536, new \DateTimeImmutable());

        return (new TodoWriteTool($this->store))->execute($call, $ctx);
    }
}
