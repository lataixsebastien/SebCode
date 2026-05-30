<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Scriptable tool double for tests.
 *
 * - `scriptOutput($text)` makes the next execute() return a successful ToolResult with that text.
 * - `scriptException($e)` makes the next execute() throw.
 * - Records every call in `$calls` so tests can assert what arguments were passed.
 */
final class FakeTool implements Tool
{
    /** @var list<array{call: ToolCall, context: ToolExecutionContext}> */
    public array $calls = [];

    /** @var list<string|\Throwable> */
    private array $script = [];

    public function __construct(
        private readonly string $name = 'fake',
        private readonly string $description = 'Test fake tool.',
    ) {
    }

    public function scriptOutput(string $text): void
    {
        $this->script[] = $text;
    }

    public function scriptException(\Throwable $e): void
    {
        $this->script[] = $e;
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of($this->name),
            $this->description,
            JsonSchema::of([
                'type' => 'object',
                'properties' => ['payload' => ['type' => 'string']],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $this->calls[] = ['call' => $call, 'context' => $context];

        if ([] === $this->script) {
            return ToolResult::success($call->id, 'fake default output');
        }

        $next = array_shift($this->script);
        if ($next instanceof InvalidToolArguments) {
            throw $next;
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return ToolResult::success($call->id, $next);
    }
}
