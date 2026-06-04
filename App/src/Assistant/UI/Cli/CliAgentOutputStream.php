<?php

declare(strict_types=1);

namespace App\Assistant\UI\Cli;

use App\Assistant\Domain\Port\AgentOutputStream;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI sink: renders the agent's progress live — the assistant's text streams in
 * raw (token by token) and each tool call/result prints on its own line.
 */
final class CliAgentOutputStream implements AgentOutputStream
{
    private bool $atLineStart = true;

    public function __construct(private readonly OutputInterface $output)
    {
    }

    public function assistantText(string $delta): void
    {
        if ('' === $delta) {
            return;
        }
        // Raw: streamed model text may contain "<", which Symfony would parse as a tag.
        $this->output->write($delta, false, OutputInterface::OUTPUT_RAW);
        $this->atLineStart = str_ends_with($delta, "\n");
    }

    public function toolCall(string $name, array $arguments): void
    {
        $args = [] === $arguments ? '' : (string) json_encode($arguments, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $this->line(\sprintf('<info>🔧 %s(%s)</info>', $name, $args));
    }

    public function toolResult(string $name, string $output, bool $isError): void
    {
        $marker = $isError ? '<error>⚠</error>' : '<info>✓</info>';
        $this->line(\sprintf('   %s %s → %s', $marker, $name, $this->oneLine($output)));
    }

    private function line(string $text): void
    {
        if (!$this->atLineStart) {
            $this->output->writeln('');
        }
        $this->output->writeln($text);
        $this->atLineStart = true;
    }

    private function oneLine(string $output): string
    {
        $oneLine = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return mb_strlen($oneLine) > 140 ? mb_substr($oneLine, 0, 137).'...' : $oneLine;
    }
}
