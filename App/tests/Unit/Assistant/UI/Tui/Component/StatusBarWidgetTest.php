<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\StatusBarWidget;
use App\Assistant\UI\Tui\TuiTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Renders the status bar through the real pipeline (VirtualTerminal) so the
 * width contract (lines must not exceed the available columns) is enforced
 * by the Renderer itself.
 */
#[CoversClass(StatusBarWidget::class)]
final class StatusBarWidgetTest extends TestCase
{
    private VirtualTerminal $terminal;
    private Tui $tui;
    private StatusBarWidget $statusBar;

    protected function setUp(): void
    {
        $this->terminal = new VirtualTerminal(columns: 80, rows: 24);
        $this->tui = new Tui(TuiTheme::styleSheet(), $this->terminal);
        $this->statusBar = new StatusBarWidget();
        $this->tui->add($this->statusBar);
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testIdleRenderShowsIdentityAndHints(): void
    {
        $this->statusBar->setIdentity('build', 'qwen2.5:3b', 'ses_0123456789abcdef');

        $output = $this->renderToText();

        self::assertStringContainsString('build · qwen2.5:3b', $output);
        self::assertStringContainsString('ses_0123456789abcdef', $output);
        self::assertStringContainsString('enter send · / commands', $output);
    }

    public function testWorkingRenderShowsSpinnerElapsedAndInterruptHint(): void
    {
        $this->statusBar->setIdentity('build', 'qwen2.5:3b', 'ses_x');
        $this->statusBar->startWorking();
        $this->statusBar->tick();

        $output = $this->renderToText();

        self::assertStringContainsString('working…', $output);
        self::assertStringContainsString('esc interrupt', $output);
        self::assertTrue($this->statusBar->isWorking());
    }

    public function testUsageIsCumulatedAndAbbreviated(): void
    {
        $this->statusBar->setIdentity('build', 'qwen2.5:3b', 'ses_x');
        $this->statusBar->addUsage(12_000, 500);
        $this->statusBar->addUsage(345, 178);

        $output = $this->renderToText();

        self::assertStringContainsString('↑12.3k ↓678 tokens', $output);
    }

    public function testArmedExitWarningReplacesHints(): void
    {
        $this->statusBar->setIdentity('build', 'qwen2.5:3b', 'ses_x');
        $this->statusBar->armExit(true);

        $output = $this->renderToText();

        self::assertStringContainsString('ctrl+c again to exit', $output);
        self::assertStringNotContainsString('enter send', $output);
    }

    public function testNoticeIsShownWhenIdle(): void
    {
        $this->statusBar->setIdentity('build', 'qwen2.5:3b', 'ses_x');
        $this->statusBar->setNotice('interrupted');

        self::assertStringContainsString('interrupted', $this->renderToText());
    }

    private function renderToText(): string
    {
        $this->tui->start();
        $this->tui->processRender();

        return (string) preg_replace('/\x1b\[[0-9;?]*[ A-Za-z]|\x1b\][^\x07]*\x07/', '', $this->terminal->getOutput());
    }
}
