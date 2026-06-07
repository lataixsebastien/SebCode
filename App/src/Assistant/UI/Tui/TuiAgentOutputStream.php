<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Assistant\Domain\Port\AgentOutputStream;
use App\Assistant\UI\Tui\Component\StatusBarWidget;
use App\Assistant\UI\Tui\Component\TranscriptView;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\MarkdownWidget;

/**
 * TUI sink: renders the agent's progress live into the transcript.
 *
 * The assistant's text accumulates into a growing MarkdownWidget (rendered
 * markdown updates as deltas arrive, like opencode's retained streaming
 * surface); tool calls and results get their own formatted entries. Each
 * update advances the status-bar spinner and forces a synchronous render —
 * the agent loop blocks the Revolt loop during a turn, so we must repaint
 * ourselves for updates to show in real time (STEP-27 mechanics).
 *
 * Interruption: while the loop is blocked nothing else consumes stdin, so
 * {@see isInterrupted()} can safely poll it non-blockingly between deltas.
 * Esc (or Ctrl+C) interrupts the generation; other keystrokes typed during
 * a turn are discarded (opencode queues them — out of scope here).
 */
final class TuiAgentOutputStream implements AgentOutputStream
{
    private ?MarkdownWidget $current = null;
    private string $buffer = '';
    private bool $interrupted = false;

    /** @var resource */
    private $stdin;

    /**
     * @param resource|null $stdin defaults to STDIN; injectable for tests
     */
    public function __construct(
        private readonly Tui $tui,
        private readonly TranscriptView $transcript,
        private readonly StatusBarWidget $statusBar,
        $stdin = null,
    ) {
        $this->stdin = \is_resource($stdin) ? $stdin : \STDIN;
    }

    public function assistantText(string $delta): void
    {
        if ('' === $delta) {
            return;
        }

        if (null === $this->current) {
            $this->current = $this->transcript->assistantStart();
            $this->buffer = '';
        }

        $this->buffer .= $delta;
        $this->current->setText($this->buffer);
        $this->render();
    }

    public function toolCall(string $name, array $arguments): void
    {
        $this->finishAssistant();
        $this->transcript->toolCall($name, $arguments);
        $this->render();
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $this->finishAssistant();
        $this->transcript->toolResult($name, $output, $isError);
        $this->render();
    }

    public function isInterrupted(): bool
    {
        if ($this->interrupted) {
            return true;
        }

        if (!stream_isatty($this->stdin)) {
            return false;
        }

        $read = [$this->stdin];
        $write = null;
        $except = null;
        if (1 > (int) @stream_select($read, $write, $except, 0)) {
            return false;
        }

        $data = fread($this->stdin, 64);
        if (\is_string($data) && (str_contains($data, "\x1b") || str_contains($data, "\x03"))) {
            $this->interrupted = true;
        }

        return $this->interrupted;
    }

    /** Reset per-turn state; called by the TUI before each send. */
    public function beginTurn(): void
    {
        $this->current = null;
        $this->buffer = '';
        $this->interrupted = false;
    }

    private function finishAssistant(): void
    {
        $this->current = null;
        $this->buffer = '';
    }

    private function render(): void
    {
        $this->statusBar->tick();
        $this->tui->requestRender(true);
        $this->tui->processRender();
    }
}
