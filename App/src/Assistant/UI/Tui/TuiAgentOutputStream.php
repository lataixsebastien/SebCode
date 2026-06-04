<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui;

use App\Assistant\Domain\Port\AgentOutputStream;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * TUI sink: renders the agent's progress live into the transcript.
 *
 * The assistant's text accumulates into a single growing widget; tool calls and
 * results get their own lines. Each update forces a synchronous render — the
 * agent loop blocks the Revolt loop during a turn, so (as with the permission
 * prompt) we must repaint ourselves for the updates to show in real time.
 */
final class TuiAgentOutputStream implements AgentOutputStream
{
    private ?TextWidget $current = null;
    private string $buffer = '';

    public function __construct(
        private readonly Tui $tui,
        private readonly ContainerWidget $transcript,
    ) {
    }

    public function assistantText(string $delta): void
    {
        if ('' === $delta) {
            return;
        }

        if (null === $this->current) {
            $this->current = (new TextWidget('◀ Assistant'))->addStyleClass('assistant');
            $this->transcript->add($this->current);
            $this->buffer = '';
        }

        $this->buffer .= $delta;
        $this->current->setText('◀ Assistant'."\n".$this->buffer."\n");
        $this->render();
    }

    public function toolCall(string $name, array $arguments): void
    {
        $this->finishAssistant();
        $args = [] === $arguments ? '' : (string) json_encode($arguments, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $this->append(\sprintf('🔧 %s(%s)', $name, $args), 'thinking');
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $this->finishAssistant();
        $marker = $isError ? '⚠' : '✓';
        $this->append(\sprintf('   %s %s', $marker, $name)."\n".$output."\n", $isError ? 'error' : 'thinking');
    }

    private function finishAssistant(): void
    {
        $this->current = null;
        $this->buffer = '';
    }

    private function append(string $text, string $cssClass): void
    {
        $this->transcript->add((new TextWidget($text))->addStyleClass($cssClass));
        $this->render();
    }

    private function render(): void
    {
        $this->tui->requestRender(true);
        $this->tui->processRender();
    }
}
