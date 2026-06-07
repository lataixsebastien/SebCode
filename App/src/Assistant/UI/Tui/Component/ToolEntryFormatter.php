<?php

declare(strict_types=1);

namespace App\Assistant\UI\Tui\Component;

/**
 * Formats tool calls and results for the transcript, opencode-style:
 * a one-line header with the tool's display name and its key argument,
 * and a truncated, indented result block.
 *
 * Pure (no TUI dependency) so it is unit-testable.
 */
final class ToolEntryFormatter
{
    public const int MAX_RESULT_LINES = 6;
    private const int MAX_SUBJECT_WIDTH = 80;

    /** Argument keys probed (in order) for the per-tool "subject" shown in the header. */
    private const array SUBJECT_KEYS = ['filePath', 'path', 'command', 'pattern', 'query', 'prompt', 'file'];

    /**
     * @param array<string, mixed> $arguments
     */
    public function callLine(string $name, array $arguments): string
    {
        $subject = $this->subject($arguments);

        return '' === $subject
            ? \sprintf('⚙ %s', $this->displayName($name))
            : \sprintf('⚙ %s  %s', $this->displayName($name), $subject);
    }

    public function resultText(string $name, string $output, bool $isError, int $maxLines = self::MAX_RESULT_LINES): string
    {
        $output = trim($output);
        if ('' === $output) {
            return \sprintf('  %s %s (no output)', $isError ? '✗' : '✓', $this->displayName($name));
        }

        $lines = preg_split('/\R/', $output) ?: [$output];
        $total = \count($lines);
        $kept = \array_slice($lines, 0, $maxLines);
        $kept = array_map(
            static fn (string $line): string => mb_strlen($line) > 200 ? mb_substr($line, 0, 197).'…' : $line,
            $kept,
        );

        $header = \sprintf('  %s %s', $isError ? '✗' : '✓', $this->displayName($name));
        $body = implode("\n", array_map(static fn (string $l): string => '    '.$l, $kept));
        if ($total > $maxLines) {
            $body .= \sprintf("\n    … +%d lines", $total - $maxLines);
        }

        return $header."\n".$body;
    }

    public function displayName(string $name): string
    {
        return match ($name) {
            'bash' => 'Bash',
            'read' => 'Read',
            'write' => 'Write',
            'edit' => 'Edit',
            'apply_patch' => 'Patch',
            'glob' => 'Glob',
            'grep' => 'Grep',
            'todowrite' => 'Todo',
            'task' => 'Task',
            'lsp' => 'Lsp',
            default => ucfirst($name),
        };
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function subject(array $arguments): string
    {
        foreach (self::SUBJECT_KEYS as $key) {
            $value = $arguments[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                return $this->truncate($value);
            }
        }

        foreach ($arguments as $value) {
            if (\is_string($value) && '' !== $value) {
                return $this->truncate($value);
            }
        }

        return '';
    }

    private function truncate(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strlen($value) > self::MAX_SUBJECT_WIDTH
            ? mb_substr($value, 0, self::MAX_SUBJECT_WIDTH - 1).'…'
            : $value;
    }
}
