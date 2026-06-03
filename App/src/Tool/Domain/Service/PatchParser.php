<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

use App\Tool\Domain\Exception\InvalidPatch;
use App\Tool\Domain\Model\FilePatch;
use App\Tool\Domain\Model\PatchHunk;
use App\Tool\Domain\Model\ValueObject\PatchOperationKind;

/**
 * Parses the OpenAI "apply_patch" envelope into structured file operations.
 *
 * Pure stdlib (Domain-clean), faithful to opencode's `Patch.parsePatch`:
 *
 *   *** Begin Patch
 *   *** Add File: <path>        (following `+` lines = new content)
 *   *** Update File: <path>     (optional `*** Move to: <path>`, then `@@` hunks)
 *   *** Delete File: <path>
 *   *** End Patch
 *
 * Hunk lines: ` ` context, `-` removed, `+` added; `*** End of File` anchors EOF.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/patch/index.ts
 */
final class PatchParser
{
    private const string BEGIN = '*** Begin Patch';
    private const string END = '*** End Patch';
    private const string ADD = '*** Add File:';
    private const string UPDATE = '*** Update File:';
    private const string DELETE = '*** Delete File:';
    private const string MOVE = '*** Move to:';
    private const string EOF = '*** End of File';

    /**
     * @return list<FilePatch>
     *
     * @throws InvalidPatch
     */
    public function parse(string $patchText): array
    {
        $lines = explode("\n", $this->stripHeredoc($patchText));
        $lines = array_map(static fn (string $l): string => rtrim($l, "\r"), $lines);

        $begin = $this->indexOfTrimmed($lines, self::BEGIN);
        $end = $this->indexOfTrimmed($lines, self::END);
        if (-1 === $begin || -1 === $end || $begin >= $end) {
            throw InvalidPatch::missingMarkers();
        }

        $inner = \array_slice($lines, $begin + 1, $end - $begin - 1);
        $patches = [];
        $i = 0;
        $n = \count($inner);

        while ($i < $n) {
            $line = $inner[$i];

            if (str_starts_with($line, self::ADD)) {
                $path = $this->after($line, self::ADD);
                [$content, $i] = $this->parseAdd($inner, $i + 1);
                $patches[] = new FilePatch(PatchOperationKind::Add, $path, content: $content);
                continue;
            }

            if (str_starts_with($line, self::DELETE)) {
                $patches[] = new FilePatch(PatchOperationKind::Delete, $this->after($line, self::DELETE));
                ++$i;
                continue;
            }

            if (str_starts_with($line, self::UPDATE)) {
                $path = $this->after($line, self::UPDATE);
                ++$i;
                $move = null;
                if ($i < $n && str_starts_with($inner[$i], self::MOVE)) {
                    $move = $this->after($inner[$i], self::MOVE);
                    ++$i;
                }
                [$hunks, $i] = $this->parseHunks($inner, $i);
                $patches[] = new FilePatch(PatchOperationKind::Update, $path, movePath: $move, hunks: $hunks);
                continue;
            }

            if ('' === trim($line)) {
                ++$i;
                continue;
            }

            throw InvalidPatch::unknownHeader($line);
        }

        if ([] === $patches) {
            throw InvalidPatch::empty();
        }

        return $patches;
    }

    /**
     * @param list<string> $lines
     *
     * @return array{0: string, 1: int}
     */
    private function parseAdd(array $lines, int $i): array
    {
        $content = [];
        $n = \count($lines);
        while ($i < $n && !$this->isFileHeader($lines[$i])) {
            if (str_starts_with($lines[$i], '+')) {
                $content[] = substr($lines[$i], 1);
            }
            ++$i;
        }

        return [implode("\n", $content), $i];
    }

    /**
     * @param list<string> $lines
     *
     * @return array{0: list<PatchHunk>, 1: int}
     */
    private function parseHunks(array $lines, int $i): array
    {
        $hunks = [];
        $n = \count($lines);

        /** @var array{old: list<string>, new: list<string>, eof: bool}|null $current */
        $current = null;
        $flush = static function () use (&$current, &$hunks): void {
            if (null !== $current) {
                $hunks[] = new PatchHunk($current['old'], $current['new'], $current['eof']);
                $current = null;
            }
        };

        while ($i < $n && !$this->isFileHeader($lines[$i])) {
            $line = $lines[$i];

            if (str_starts_with($line, '@@')) {
                $flush();
                $current = ['old' => [], 'new' => [], 'eof' => false];
                ++$i;
                continue;
            }

            $current ??= ['old' => [], 'new' => [], 'eof' => false];

            if (self::EOF === trim($line)) {
                $current['eof'] = true;
            } elseif (str_starts_with($line, '-')) {
                $current['old'][] = substr($line, 1);
            } elseif (str_starts_with($line, '+')) {
                $current['new'][] = substr($line, 1);
            } else {
                // Context line: " foo", or a bare empty line.
                $value = str_starts_with($line, ' ') ? substr($line, 1) : $line;
                $current['old'][] = $value;
                $current['new'][] = $value;
            }
            ++$i;
        }

        $flush();

        return [$hunks, $i];
    }

    private function isFileHeader(string $line): bool
    {
        return str_starts_with($line, self::ADD)
            || str_starts_with($line, self::UPDATE)
            || str_starts_with($line, self::DELETE);
    }

    private function after(string $line, string $prefix): string
    {
        return trim(substr($line, \strlen($prefix)));
    }

    /**
     * @param list<string> $lines
     */
    private function indexOfTrimmed(array $lines, string $marker): int
    {
        foreach ($lines as $i => $line) {
            if (trim($line) === $marker) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Unwrap a bash heredoc the LLM may have wrapped the patch in
     * (`cat <<'EOF' … EOF`).
     */
    private function stripHeredoc(string $text): string
    {
        $trimmed = trim($text);
        if (1 === preg_match('~^(?:cat\s+)?<<[\'"]?(\w+)[\'"]?\s*\n(.*)\n\1\s*$~s', $trimmed, $m)) {
            return $m[2];
        }

        return $text;
    }
}
