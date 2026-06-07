<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\UI\Tui\Component;

use App\Assistant\UI\Tui\Component\TranscriptView;
use App\Assistant\UI\Tui\TuiTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Renders transcript entries through the real pipeline (VirtualTerminal):
 * exercises the markdown widget, the per-tool formatting and the splash so
 * a width-contract violation or a missing dependency fails loudly here.
 */
#[CoversClass(TranscriptView::class)]
final class TranscriptViewTest extends TestCase
{
    private VirtualTerminal $terminal;
    private Tui $tui;
    private TranscriptView $view;

    protected function setUp(): void
    {
        $this->terminal = new VirtualTerminal(columns: 100, rows: 40);
        $this->tui = new Tui(TuiTheme::styleSheet(), $this->terminal);
        $container = (new ContainerWidget())->addStyleClass('transcript');
        $this->view = new TranscriptView($container);
        $this->tui->add($container);
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testSplashShowsLogoAndSessionInfo(): void
    {
        $this->view->splash('qwen2.5:3b', 'ses_42', 'TUI chat');

        $output = $this->renderToText();

        self::assertStringContainsString('█▀▀', $output);
        self::assertStringContainsString('TUI chat · qwen2.5:3b', $output);
        self::assertStringContainsString('/help for commands', $output);
    }

    public function testFullConversationRendersWithoutWidthViolations(): void
    {
        $this->view->user("explain this\nproject");
        $this->view->assistantMarkdown("# Title\n\nSome **bold** text\n\n```php\necho 'hi';\n```");
        $this->view->toolCall('bash', ['command' => 'ls -la']);
        $this->view->toolResult('bash', "file1\nfile2", false);
        $this->view->toolCall('read', ['filePath' => 'src/Kernel.php']);
        $this->view->toolResult('grep', 'permission denied', true);
        $this->view->system('permission bash → allowed once');
        $this->view->error('LLM unavailable: connection refused');
        $this->view->help();

        $output = $this->renderToText();

        self::assertStringContainsString('explain this', $output);
        self::assertStringContainsString('Title', $output);
        self::assertStringContainsString('⚙ Bash  ls -la', $output);
        self::assertStringContainsString('✓ Bash', $output);
        self::assertStringContainsString('⚙ Read  src/Kernel.php', $output);
        self::assertStringContainsString('✗ Grep', $output);
        self::assertStringContainsString('✗ LLM unavailable', $output);
        self::assertStringContainsString('/models', $output);
    }

    public function testStreamingAssistantEntryUpdatesInPlace(): void
    {
        $widget = $this->view->assistantStart();
        $widget->setText('Hello');
        $widget->setText('Hello **world**');

        $output = $this->renderToText();

        self::assertStringContainsString('Hello', $output);
        self::assertStringContainsString('world', $output);
    }

    public function testClearEmptiesTheTranscript(): void
    {
        $this->view->user('hello');
        $this->view->clear();

        self::assertStringNotContainsString('hello', $this->renderToText());
    }

    private function renderToText(): string
    {
        $this->tui->start();
        $this->tui->processRender();

        return (string) preg_replace('/\x1b\[[0-9;?]*[ A-Za-z]|\x1b\][^\x07]*\x07/', '', $this->terminal->getOutput());
    }
}
