<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

use App\Tool\Domain\Exception\PatchApplyFailed;
use App\Tool\Domain\Model\PatchHunk;

/**
 * Applies a sequence of {@see PatchHunk}s to a file's content.
 *
 * Faithful to opencode's patch apply: locate each hunk's `oldLines` with
 * progressively looser matching (exact → rstrip → trim → unicode-normalised),
 * anchoring at EOF when asked, then splice in the `newLines`. Replacements are
 * applied back-to-front so earlier indices stay valid. Pure stdlib.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/patch/index.ts (seekSequence)
 */
final class PatchApplier
{
    /**
     * @param list<PatchHunk> $hunks
     *
     * @throws PatchApplyFailed when a hunk's context cannot be located
     */
    public function apply(string $original, array $hunks, string $path): string
    {
        $lines = explode("\n", $original);

        /** @var list<array{start: int, length: int, replacement: list<string>}> $replacements */
        $replacements = [];
        $cursor = 0;

        foreach ($hunks as $hunk) {
            $pattern = $hunk->oldLines;
            $length = \count($pattern);
            $found = $this->seek($lines, $pattern, $cursor, $hunk->isEndOfFile);

            // Retry without a trailing empty context line (a common drift).
            if (-1 === $found && $length > 0 && '' === $pattern[$length - 1]) {
                array_pop($pattern);
                --$length;
                $found = $this->seek($lines, $pattern, $cursor, $hunk->isEndOfFile);
            }

            if (-1 === $found) {
                throw PatchApplyFailed::contextNotFound($path);
            }

            $replacements[] = ['start' => $found, 'length' => $length, 'replacement' => $hunk->newLines];
            $cursor = $found + $length;
        }

        usort($replacements, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);
        foreach ($replacements as $r) {
            array_splice($lines, $r['start'], $r['length'], $r['replacement']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param list<string> $pattern
     */
    private function seek(array $lines, array $pattern, int $startIndex, bool $endOfFile): int
    {
        $patLen = \count($pattern);
        if (0 === $patLen) {
            return $startIndex;
        }

        $n = \count($lines);
        $last = $n - $patLen;

        foreach ([null, 'rtrim', 'trim', 'unicode'] as $mode) {
            // EOF-anchored hunks look at the tail first.
            if ($endOfFile && $last >= $startIndex && $this->matchAt($lines, $pattern, $last, $mode)) {
                return $last;
            }
            for ($i = $startIndex; $i <= $last; ++$i) {
                if ($this->matchAt($lines, $pattern, $i, $mode)) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * @param list<string> $lines
     * @param list<string> $pattern
     */
    private function matchAt(array $lines, array $pattern, int $at, ?string $mode): bool
    {
        foreach ($pattern as $k => $expected) {
            if ($this->normalize($lines[$at + $k], $mode) !== $this->normalize($expected, $mode)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value, ?string $mode): string
    {
        return match ($mode) {
            'rtrim' => rtrim($value),
            'trim' => trim($value),
            'unicode' => trim($this->normalizeUnicode($value)),
            default => $value,
        };
    }

    private function normalizeUnicode(string $value): string
    {
        return strtr($value, [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{201F}" => '"',
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-',
            "\u{2014}" => '-', "\u{2015}" => '-',
            "\u{2026}" => '...',
            "\u{00A0}" => ' ',
        ]);
    }
}
