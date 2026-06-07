<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Always-visible "Steps" panel: the agent's todo list (todowrite tool),
 * drawn as a rounded box aligned to the right, opencode-style:
 *
 *                              ╭─ Steps ─────────── 2/5 ─╮
 *                              │ ✓ scan the codebase     │
 *                              │ ✓ write the failing test│
 *                              │ ▸ implement the fix     │
 *                              │ ○ run the test suite    │
 *                              │ ○ update the docs       │
 *                              ╰─────────────────────────╯
 *
 * Lives in the footer (sticky) rather than the scrollback: symfony/tui
 * renders one line flow viewed from the bottom, so a true side column
 * would scroll away with the transcript. Renders nothing when empty.
 */
final class StepsPanelWidget extends AbstractWidget
{
    private const int MAX_VISIBLE = 8;
    private const int PANEL_WIDTH = 36;

    /** @var list<TodoItem> */
    private array $todos = [];

    /**
     * @param list<TodoItem> $todos
     */
    public function setTodos(array $todos): void
    {
        $this->todos = $todos;
        $this->invalidate();
        $this->getContext()?->requestRender();
    }

    /** @return list<TodoItem> */
    public function getTodos(): array
    {
        return $this->todos;
    }

    public function render(RenderContext $context): array
    {
        if ([] === $this->todos) {
            return [];
        }

        $columns = $context->getColumns();
        $width = min(self::PANEL_WIDTH, $columns);
        $inner = $width - 4; // '│ ' + ' │'
        if ($inner < 8) {
            return [];
        }

        $leftPad = str_repeat(' ', max(0, $columns - $width));
        $border = $this->resolveElement('border');
        $title = $this->resolveElement('title');
        $counter = $this->resolveElement('counter');

        $done = \count(array_filter(
            $this->todos,
            static fn (TodoItem $t): bool => TodoStatus::Completed === $t->status,
        ));
        $countText = \sprintf('%d/%d', $done, \count($this->todos));

        // ╭─ Steps ───────── 2/5 ─╮
        $head = '─ '.$title->apply('Steps').' ';
        $tail = ' '.$counter->apply($countText).' ';
        $fill = max(0, $width - 2 - AnsiUtils::visibleWidth($head) - AnsiUtils::visibleWidth($tail) - 1);
        $lines = [$leftPad.$border->apply('╭').$head.$border->apply(str_repeat('─', $fill)).$tail.$border->apply('─╮')];

        foreach ($this->visibleTodos() as $todo) {
            $lines[] = $leftPad
                .$border->apply('│ ')
                .$this->todoLine($todo, $inner)
                .$border->apply(' │');
        }

        if (\count($this->todos) > self::MAX_VISIBLE) {
            $more = \sprintf('… +%d more', \count($this->todos) - self::MAX_VISIBLE);
            $lines[] = $leftPad
                .$border->apply('│ ')
                .$this->pad($this->resolveElement('pending')->apply($more), $inner, AnsiUtils::visibleWidth($more))
                .$border->apply(' │');
        }

        $lines[] = $leftPad.$border->apply('╰'.str_repeat('─', $width - 2).'╯');

        return $lines;
    }

    /**
     * Keep the panel focused on actionable work: when the list overflows,
     * show the tail of completed items plus everything still to do.
     *
     * @return list<TodoItem>
     */
    private function visibleTodos(): array
    {
        $count = \count($this->todos);
        if ($count <= self::MAX_VISIBLE) {
            return $this->todos;
        }

        $firstOpen = 0;
        foreach ($this->todos as $i => $todo) {
            if (TodoStatus::Completed !== $todo->status && TodoStatus::Cancelled !== $todo->status) {
                $firstOpen = $i;
                break;
            }
        }

        $start = min($firstOpen, $count - self::MAX_VISIBLE);

        return \array_slice($this->todos, max(0, $start), self::MAX_VISIBLE);
    }

    private function todoLine(TodoItem $todo, int $inner): string
    {
        [$marker, $element] = match ($todo->status) {
            TodoStatus::Completed => ['✓', 'done'],
            TodoStatus::InProgress => ['▸', 'active'],
            TodoStatus::Cancelled => ['✗', 'cancelled'],
            TodoStatus::Pending => ['○', 'pending'],
        };

        $text = $marker.' '.$todo->content;
        $truncated = AnsiUtils::truncateToWidth($text, $inner, '…');

        return $this->pad(
            $this->resolveElement($element)->apply($truncated),
            $inner,
            AnsiUtils::visibleWidth($truncated),
        );
    }

    private function pad(string $styled, int $inner, int $visibleWidth): string
    {
        return $styled.str_repeat(' ', max(0, $inner - $visibleWidth));
    }
}
