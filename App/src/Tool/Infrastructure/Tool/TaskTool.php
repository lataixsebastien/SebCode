<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\SubAgentRunner;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Delegate a focused sub-task to a fresh nested agent.
 *
 * Mirrors opencode's `task`: the LLM hands off a self-contained instruction;
 * a bounded sub-agent runs with the normal tools (minus `task`, so no
 * recursion) and returns a single textual answer. Its intermediate steps are
 * not persisted and not shown to the user — only the returned text. No
 * permission of its own; any mutating tool the sub-agent calls is gated as
 * usual.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/task.ts
 */
final readonly class TaskTool implements Tool
{
    public function __construct(private SubAgentRunner $runner)
    {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('task'),
            'Delegate a self-contained sub-task to a fresh agent that has the same tools (except task). '
                .'Use it for focused, multi-step work you can describe completely up front (e.g. "find where '
                .'X is implemented and summarise it"). The sub-agent returns a single text answer; its steps '
                .'are not shown. Give it everything it needs — it starts with no prior context.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'A short (3-5 words) description of the task.',
                    ],
                    'prompt' => [
                        'type' => 'string',
                        'description' => 'The full, self-contained instruction for the sub-agent.',
                    ],
                    'subagent_type' => [
                        'type' => 'string',
                        'description' => 'Optional agent type hint (currently a single general-purpose agent).',
                    ],
                ],
                'required' => ['description', 'prompt'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $description = $call->arguments['description'] ?? null;
        if (!\is_string($description) || '' === trim($description)) {
            throw InvalidToolArguments::for($call->name, 'argument "description" must be a non-empty string');
        }

        $prompt = $call->arguments['prompt'] ?? null;
        if (!\is_string($prompt) || '' === trim($prompt)) {
            throw InvalidToolArguments::for($call->name, 'argument "prompt" must be a non-empty string');
        }

        $answer = $this->runner->run($prompt, $context->sessionId);

        return ToolResult::success(
            $call->id,
            '' === trim($answer) ? '(sub-agent returned no answer)' : $answer,
            ['description' => trim($description)],
        );
    }
}
