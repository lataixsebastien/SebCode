<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\TodoStore;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Maintain the agent's todo list for the current session.
 *
 * Faithful port of opencode's `todowrite`: the call carries the FULL updated
 * list, which replaces the stored one ({@see TodoStore}). Read-only on disk
 * (no permission needed); the only side effect is the in-memory list. Returns
 * a rendered checklist so both the LLM (in history) and the user (CLI/TUI) see
 * the current state.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/todo.ts
 */
final readonly class TodoWriteTool implements Tool
{
    public function __construct(private TodoStore $store)
    {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('todowrite'),
            'Create and maintain a structured task list for the current session. Send the FULL list '
                .'each call; it replaces the previous one. Use it for multi-step work (3+ steps): mark a '
                .'task in_progress before starting it (only one at a time) and completed as soon as it is '
                .'actually done. Skip it for trivial or purely conversational requests.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'todos' => [
                        'type' => 'array',
                        'description' => 'The complete, updated todo list (replaces the previous one).',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'content' => [
                                    'type' => 'string',
                                    'description' => 'Specific, actionable description of the task.',
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'enum' => ['pending', 'in_progress', 'completed', 'cancelled'],
                                    'description' => 'Current status of the task.',
                                ],
                                'priority' => [
                                    'type' => 'string',
                                    'enum' => ['high', 'medium', 'low'],
                                    'description' => 'Priority of the task.',
                                ],
                            ],
                            'required' => ['content', 'status', 'priority'],
                        ],
                    ],
                ],
                'required' => ['todos'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $todos = $call->arguments['todos'] ?? null;
        if (!\is_array($todos)) {
            throw InvalidToolArguments::for($call->name, 'argument "todos" must be an array');
        }

        $items = [];
        foreach (array_values($todos) as $index => $raw) {
            if (!\is_array($raw)) {
                return ToolResult::failure($call->id, \sprintf('todo #%d must be an object', $index + 1));
            }

            $content = $raw['content'] ?? null;
            if (!\is_string($content) || '' === trim($content)) {
                return ToolResult::failure($call->id, \sprintf('todo #%d: "content" must be a non-empty string', $index + 1));
            }

            $status = \is_string($raw['status'] ?? null) ? TodoStatus::tryFrom($raw['status']) : null;
            if (null === $status) {
                return ToolResult::failure($call->id, \sprintf('todo #%d: "status" must be one of pending, in_progress, completed, cancelled', $index + 1));
            }

            $priority = \is_string($raw['priority'] ?? null) ? TodoPriority::tryFrom($raw['priority']) : null;
            if (null === $priority) {
                return ToolResult::failure($call->id, \sprintf('todo #%d: "priority" must be one of high, medium, low', $index + 1));
            }

            $items[] = new TodoItem(trim($content), $status, $priority);
        }

        $this->store->replace($items);

        return ToolResult::success($call->id, $this->render($items), ['todos' => $this->toArray($items)]);
    }

    /**
     * @param list<TodoItem> $items
     */
    private function render(array $items): string
    {
        $open = \count(array_filter($items, static fn (TodoItem $t): bool => TodoStatus::Completed !== $t->status));
        $lines = [\sprintf('%d todos · %d open', \count($items), $open)];

        foreach ($items as $item) {
            $lines[] = \sprintf('  [%s] %s', $this->marker($item->status), $item->content);
        }

        return implode("\n", $lines);
    }

    private function marker(TodoStatus $status): string
    {
        return match ($status) {
            TodoStatus::Completed => '✓',
            TodoStatus::InProgress => '•',
            TodoStatus::Cancelled => '✗',
            TodoStatus::Pending => ' ',
        };
    }

    /**
     * @param list<TodoItem> $items
     *
     * @return list<array{content: string, status: string, priority: string}>
     */
    private function toArray(array $items): array
    {
        return array_map(static fn (TodoItem $t): array => [
            'content' => $t->content,
            'status' => $t->status->value,
            'priority' => $t->priority->value,
        ], $items);
    }
}
