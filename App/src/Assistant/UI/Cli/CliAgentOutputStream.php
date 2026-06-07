<?php

declare(strict_types=1);

namespace App\Assistant\UI\Cli;

use App\Assistant\Domain\Port\AgentOutputStream;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI sink: renders the agent's progress live, opencode-style.
 *
 * A turn opens with an "● Assistant" header (preceded by a dim activity hint
 * while the model loads/thinks, on decorated terminals). The reply text streams
 * in raw, token by token; each tool call/result prints as an indented, coloured
 * step (`│ ⚙ read  path` / `│   ✓ read → …`). All colour goes through Symfony's
 * formatter, which strips tags on non-decorated output; the only hand-written
 * ANSI (the activity-hint erase) is guarded by {@see OutputInterface::isDecorated()}.
 */
final class CliAgentOutputStream implements AgentOutputStream
{
    private bool $atLineStart = true;
    private bool $interrupted = false;
    private bool $thinking = false;
    private bool $headerShown = false;

    /**
     * @param bool $interruptible when true, {@see isInterrupted()} polls STDIN so
     *                            the user can stop a running generation by pressing
     *                            Enter. Only enable for an interactive TTY (the
     *                            chat REPL); a piped/one-shot run must leave it off,
     *                            or buffered/EOF input would abort instantly.
     */
    public function __construct(
        private readonly OutputInterface $output,
        private readonly bool $interruptible = false,
    ) {
    }

    /**
     * Open a turn: reset per-turn state and, on a decorated terminal, show a dim
     * activity hint so a loading/thinking model does not look frozen. The hint is
     * erased in place the moment the first text or tool step arrives.
     */
    public function startTurn(): void
    {
        $this->headerShown = false;
        $this->thinking = false;
        $this->atLineStart = true;

        $this->output->writeln('');
        if ($this->output->isDecorated()) {
            $this->output->write('<fg=green>● Assistant</> <fg=gray>⏳ réflexion…</>');
            $this->thinking = true;
        }
    }

    /** Close a turn: drop a leftover activity hint if the model produced nothing. */
    public function endTurn(): void
    {
        $this->clearThinking();
    }

    public function assistantText(string $delta): void
    {
        if ('' === $delta) {
            return;
        }
        $this->ensureHeader();
        // Raw: streamed model text may contain "<", which Symfony would parse as a tag.
        $this->output->write($delta, false, OutputInterface::OUTPUT_RAW);
        $this->atLineStart = str_ends_with($delta, "\n");
    }

    public function toolCall(string $name, array $arguments): void
    {
        $this->ensureHeader();
        $this->line(\sprintf('<fg=cyan>│ ⚙ %s</>  <fg=gray>%s</>', $name, $this->summarizeArgs($arguments)));
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $this->ensureHeader();
        $marker = $isError ? '<fg=red>│   ⚠</>' : '<fg=green>│   ✓</>';
        $this->line(\sprintf('%s <fg=gray>%s → %s</>', $marker, $name, $this->oneLine($output)));
    }

    public function isInterrupted(): bool
    {
        if ($this->interrupted || !$this->interruptible) {
            return $this->interrupted;
        }

        // Cooked-mode console: a keystroke only reaches us once the user hits
        // Enter, which makes a line available on STDIN. Poll without blocking;
        // any pending line means "stop", and we drain it so it is not read back
        // as the next prompt.
        $read = [\STDIN];
        $write = [];
        $except = [];
        // stream_select returns the number of ready streams (>0 ⇒ input pending);
        // false on error, 0 on none — both falsy, so a plain truthiness check fits.
        if (@stream_select($read, $write, $except, 0)) {
            fgets(\STDIN);
            $this->interrupted = true;
        }

        return $this->interrupted;
    }

    /** Erase the activity hint (if any) and print the assistant header once per turn. */
    private function ensureHeader(): void
    {
        $this->clearThinking();
        if (!$this->headerShown) {
            $this->output->writeln('<fg=green>● Assistant</>');
            $this->headerShown = true;
            $this->atLineStart = true;
        }
    }

    private function clearThinking(): void
    {
        if (!$this->thinking) {
            return;
        }
        $this->thinking = false;
        if ($this->output->isDecorated()) {
            // Return to column 0 and clear the whole line (the activity hint).
            $this->output->write("\r\033[2K", false, OutputInterface::OUTPUT_RAW);
        }
        $this->atLineStart = true;
    }

    private function line(string $text): void
    {
        if (!$this->atLineStart) {
            $this->output->writeln('');
        }
        $this->output->writeln($text);
        $this->atLineStart = true;
    }

    /**
     * Compact, human-readable view of tool arguments: prefer the one field that
     * carries the intent (path/pattern/command), else a short JSON blob.
     *
     * @param array<string, mixed> $arguments
     */
    private function summarizeArgs(array $arguments): string
    {
        foreach (['filePath', 'path', 'pattern', 'command', 'file', 'query'] as $key) {
            if (isset($arguments[$key]) && \is_scalar($arguments[$key])) {
                return (string) $arguments[$key];
            }
        }
        if ([] === $arguments) {
            return '';
        }

        $json = (string) json_encode($arguments, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return mb_strlen($json) > 80 ? mb_substr($json, 0, 77).'…' : $json;
    }

    private function oneLine(string $output): string
    {
        $oneLine = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return mb_strlen($oneLine) > 140 ? mb_substr($oneLine, 0, 137).'…' : $oneLine;
    }
}
