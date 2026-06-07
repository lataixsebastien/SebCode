<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\StepsPanelWidget;
use App\Assistant\UI\Tui\TuiTheme;
use App\Tool\Domain\Model\TodoItem;
use App\Tool\Domain\Model\ValueObject\TodoPriority;
use App\Tool\Domain\Model\ValueObject\TodoStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Renders the Steps panel through the real pipeline (VirtualTerminal) so the
 * width contract is enforced by the Renderer itself.
 */
#[CoversClass(StepsPanelWidget::class)]
final class StepsPanelWidgetTest extends TestCase
{
    private VirtualTerminal $terminal;
    private Tui $tui;
    private StepsPanelWidget $panel;

    protected function setUp(): void
    {
        $this->terminal = new VirtualTerminal(columns: 80, rows: 30);
        $this->tui = new Tui(TuiTheme::styleSheet(), $this->terminal);
        $this->panel = new StepsPanelWidget();
        $this->tui->add($this->panel);
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testRendersNothingWhenEmpty(): void
    {
        self::assertSame('', trim($this->renderToText()));
    }

    public function testRendersTitleCounterAndStatusMarkers(): void
    {
        $this->panel->setTodos([
            $this->todo('scan the codebase', TodoStatus::Completed),
            $this->todo('implement the fix', TodoStatus::InProgress),
            $this->todo('run the test suite', TodoStatus::Pending),
            $this->todo('obsolete idea', TodoStatus::Cancelled),
        ]);

        $output = $this->renderToText();

        self::assertStringContainsString('Steps', $output);
        self::assertStringContainsString('1/4', $output);
        self::assertStringContainsString('✓ scan the codebase', $output);
        self::assertStringContainsString('▸ implement the fix', $output);
        self::assertStringContainsString('○ run the test suite', $output);
        self::assertStringContainsString('✗ obsolete idea', $output);
        self::assertStringContainsString('╭─', $output);
        self::assertStringContainsString('╰─', $output);
    }

    public function testIsRightAligned(): void
    {
        $this->panel->setTodos([$this->todo('one step', TodoStatus::Pending)]);

        $output = $this->renderToText();
        $boxLine = null;
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, '○ one step')) {
                $boxLine = $line;
                break;
            }
        }

        self::assertNotNull($boxLine);
        // 80 columns, 36-wide panel → at least 40 leading spaces.
        self::assertMatchesRegularExpression('/^\s{40,}/', $boxLine);
    }

    public function testOverflowKeepsOpenItemsVisibleAndCountsTheRest(): void
    {
        $todos = [];
        for ($i = 1; $i <= 7; ++$i) {
            $todos[] = $this->todo("done step $i", TodoStatus::Completed);
        }
        for ($i = 1; $i <= 5; ++$i) {
            $todos[] = $this->todo("open step $i", TodoStatus::Pending);
        }
        $this->panel->setTodos($todos);

        $output = $this->renderToText();

        self::assertStringContainsString('7/12', $output);
        // All 5 open items are visible; the early done items scrolled out.
        self::assertStringContainsString('○ open step 5', $output);
        self::assertStringContainsString('… +4 more', $output);
        self::assertStringNotContainsString('done step 1', $output);
    }

    public function testLongContentIsTruncatedInsideTheBox(): void
    {
        $this->panel->setTodos([
            $this->todo(str_repeat('very long step ', 10), TodoStatus::InProgress),
        ]);

        $output = $this->renderToText();

        self::assertStringContainsString('…', $output);
    }

    private function todo(string $content, TodoStatus $status): TodoItem
    {
        return new TodoItem($content, $status, TodoPriority::Medium);
    }

    private function renderToText(): string
    {
        $this->tui->start();
        $this->tui->processRender();

        return (string) preg_replace('/\x1b\[[0-9;?]*[ A-Za-z]|\x1b\][^\x07]*\x07/', '', $this->terminal->getOutput());
    }
}
