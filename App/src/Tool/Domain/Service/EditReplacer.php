<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

use App\Tool\Domain\Exception\EditConflict;

/**
 * Applies a string replacement to file content, tolerating small mismatches
 * between the LLM's `oldString` and the real bytes.
 *
 * Faithful PHP port of opencode's edit.ts replacer chain
 * (packages/opencode/src/tool/edit.ts:213-711). The nine replacers are tried
 * in order, from exact match to increasingly fuzzy strategies; the first one
 * that yields a candidate present in the content wins. With replaceAll=false a
 * candidate must be unique, otherwise we move on (and ultimately raise
 * EditConflict::multipleMatches). Nothing matched anywhere → notFound.
 *
 * Byte-based (PHP string ops) rather than UTF-16; fine for source editing.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/edit.ts
 */
final class EditReplacer
{
    private const float SINGLE_CANDIDATE_SIMILARITY_THRESHOLD = 0.0;
    private const float MULTIPLE_CANDIDATES_SIMILARITY_THRESHOLD = 0.3;

    /**
     * @throws EditConflict on no-op, no match, or ambiguous match
     */
    public function replace(string $content, string $oldString, string $newString, bool $replaceAll = false): string
    {
        if ($oldString === $newString) {
            throw EditConflict::noChange();
        }

        $notFound = true;
        $chain = [
            $this->simple($content, $oldString),
            $this->lineTrimmed($content, $oldString),
            $this->blockAnchor($content, $oldString),
            $this->whitespaceNormalized($content, $oldString),
            $this->indentationFlexible($content, $oldString),
            $this->escapeNormalized($content, $oldString),
            $this->trimmedBoundary($content, $oldString),
            $this->contextAware($content, $oldString),
            $this->multiOccurrence($content, $oldString),
        ];

        foreach ($chain as $candidates) {
            foreach ($candidates as $search) {
                if ('' === $search) {
                    continue;
                }
                $index = strpos($content, $search);
                if (false === $index) {
                    continue;
                }
                $notFound = false;
                if ($replaceAll) {
                    return str_replace($search, $newString, $content);
                }
                if ($index !== strrpos($content, $search)) {
                    continue;
                }

                return substr($content, 0, $index).$newString.substr($content, $index + \strlen($search));
            }
        }

        throw $notFound ? EditConflict::notFound() : EditConflict::multipleMatches();
    }

    /**
     * @return iterable<string>
     */
    private function simple(string $content, string $find): iterable
    {
        yield $find;
    }

    /**
     * @return iterable<string>
     */
    private function lineTrimmed(string $content, string $find): iterable
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);
        if ('' === end($searchLines)) {
            array_pop($searchLines);
        }
        $m = \count($searchLines);
        if (0 === $m) {
            return;
        }

        for ($i = 0, $n = \count($originalLines); $i <= $n - $m; ++$i) {
            $matches = true;
            for ($j = 0; $j < $m; ++$j) {
                if (trim($originalLines[$i + $j]) !== trim($searchLines[$j])) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                yield implode("\n", \array_slice($originalLines, $i, $m));
            }
        }
    }

    /**
     * Fuzzy block match anchored on the first and last lines, scoring the
     * middle with Levenshtein similarity.
     *
     * @return iterable<string>
     */
    private function blockAnchor(string $content, string $find): iterable
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);
        if (\count($searchLines) < 3) {
            return;
        }
        if ('' === end($searchLines)) {
            array_pop($searchLines);
        }

        $firstLineSearch = trim($searchLines[0]);
        $lastLineSearch = trim($searchLines[\count($searchLines) - 1]);
        $searchBlockSize = \count($searchLines);

        /** @var list<array{start: int, end: int}> $candidates */
        $candidates = [];
        $n = \count($originalLines);
        for ($i = 0; $i < $n; ++$i) {
            if (trim($originalLines[$i]) !== $firstLineSearch) {
                continue;
            }
            for ($j = $i + 2; $j < $n; ++$j) {
                if (trim($originalLines[$j]) === $lastLineSearch) {
                    $candidates[] = ['start' => $i, 'end' => $j];
                    break;
                }
            }
        }

        if ([] === $candidates) {
            return;
        }

        if (1 === \count($candidates)) {
            $similarity = $this->middleSimilarity(
                $originalLines,
                $searchLines,
                $candidates[0],
                $searchBlockSize,
                true,
            );
            if ($similarity >= self::SINGLE_CANDIDATE_SIMILARITY_THRESHOLD) {
                yield implode("\n", \array_slice($originalLines, $candidates[0]['start'], $candidates[0]['end'] - $candidates[0]['start'] + 1));
            }

            return;
        }

        $best = null;
        $maxSimilarity = -1.0;
        foreach ($candidates as $candidate) {
            $similarity = $this->middleSimilarity($originalLines, $searchLines, $candidate, $searchBlockSize, false);
            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $best = $candidate;
            }
        }

        if (null !== $best && $maxSimilarity >= self::MULTIPLE_CANDIDATES_SIMILARITY_THRESHOLD) {
            yield implode("\n", \array_slice($originalLines, $best['start'], $best['end'] - $best['start'] + 1));
        }
    }

    /**
     * @param list<string> $originalLines
     * @param list<string> $searchLines
     * @param array{start: int, end: int} $candidate
     */
    private function middleSimilarity(
        array $originalLines,
        array $searchLines,
        array $candidate,
        int $searchBlockSize,
        bool $averagePerLine,
    ): float {
        $actualBlockSize = $candidate['end'] - $candidate['start'] + 1;
        $linesToCheck = min($searchBlockSize - 2, $actualBlockSize - 2);
        if ($linesToCheck <= 0) {
            return 1.0;
        }

        $similarity = 0.0;
        for ($j = 1; $j < $searchBlockSize - 1 && $j < $actualBlockSize - 1; ++$j) {
            $originalLine = trim($originalLines[$candidate['start'] + $j]);
            $searchLine = trim($searchLines[$j]);
            $maxLen = max(\strlen($originalLine), \strlen($searchLine));
            if (0 === $maxLen) {
                continue;
            }
            $lineScore = 1 - $this->levenshtein($originalLine, $searchLine) / $maxLen;
            if ($averagePerLine) {
                // Single-candidate path divides per line as it accumulates.
                $similarity += $lineScore / $linesToCheck;
                if ($similarity >= self::SINGLE_CANDIDATE_SIMILARITY_THRESHOLD) {
                    break;
                }
            } else {
                $similarity += $lineScore;
            }
        }

        return $averagePerLine ? $similarity : $similarity / $linesToCheck;
    }

    /**
     * @return iterable<string>
     */
    private function whitespaceNormalized(string $content, string $find): iterable
    {
        $normalize = static fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', $text));
        $normalizedFind = $normalize($find);
        if ('' === $normalizedFind) {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if ($normalize($line) === $normalizedFind) {
                yield $line;
                continue;
            }
            if (!str_contains($normalize($line), $normalizedFind)) {
                continue;
            }
            $words = preg_split('/\s+/', trim($find)) ?: [];
            if ([] === $words || '' === $words[0]) {
                continue;
            }
            $pattern = '#'.implode('\s+', array_map(static fn (string $w): string => preg_quote($w, '#'), $words)).'#';
            if (1 === preg_match($pattern, $line, $match)) {
                yield $match[0];
            }
        }

        $findLines = explode("\n", $find);
        if (\count($findLines) > 1) {
            $count = \count($lines);
            for ($i = 0; $i <= $count - \count($findLines); ++$i) {
                $block = \array_slice($lines, $i, \count($findLines));
                if ($normalize(implode("\n", $block)) === $normalizedFind) {
                    yield implode("\n", $block);
                }
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function indentationFlexible(string $content, string $find): iterable
    {
        $removeIndent = static function (string $text): string {
            $lines = explode("\n", $text);
            $nonEmpty = array_filter($lines, static fn (string $l): bool => '' !== trim($l));
            if ([] === $nonEmpty) {
                return $text;
            }
            $min = \PHP_INT_MAX;
            foreach ($nonEmpty as $line) {
                preg_match('/^(\s*)/', $line, $m);
                $min = min($min, \strlen($m[1] ?? ''));
            }

            return implode("\n", array_map(
                static fn (string $l): string => '' === trim($l) ? $l : substr($l, $min),
                $lines,
            ));
        };

        $normalizedFind = $removeIndent($find);
        $contentLines = explode("\n", $content);
        $findLines = explode("\n", $find);
        $fc = \count($findLines);
        for ($i = 0, $n = \count($contentLines); $i <= $n - $fc; ++$i) {
            $block = implode("\n", \array_slice($contentLines, $i, $fc));
            if ($removeIndent($block) === $normalizedFind) {
                yield $block;
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function escapeNormalized(string $content, string $find): iterable
    {
        $unescape = static function (string $str): string {
            $pattern = '~\\\\(n|t|r|\'|"|`|\\\\|'."\n".'|\$)~';

            return (string) preg_replace_callback($pattern, static function (array $m): string {
                /** @var array{0: string, 1: string} $m */
                return match ($m[1]) {
                    'n', "\n" => "\n",
                    't' => "\t",
                    'r' => "\r",
                    "'" => "'",
                    '"' => '"',
                    '`' => '`',
                    '\\' => '\\',
                    '$' => '$',
                    default => $m[0],
                };
            }, $str);
        };

        $unescapedFind = $unescape($find);
        if (str_contains($content, $unescapedFind)) {
            yield $unescapedFind;
        }

        $lines = explode("\n", $content);
        $findLines = explode("\n", $unescapedFind);
        $fc = \count($findLines);
        for ($i = 0, $n = \count($lines); $i <= $n - $fc; ++$i) {
            $block = implode("\n", \array_slice($lines, $i, $fc));
            if ($unescape($block) === $unescapedFind) {
                yield $block;
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function trimmedBoundary(string $content, string $find): iterable
    {
        $trimmed = trim($find);
        if ($trimmed === $find) {
            return;
        }
        if (str_contains($content, $trimmed)) {
            yield $trimmed;
        }

        $lines = explode("\n", $content);
        $findLines = explode("\n", $find);
        $fc = \count($findLines);
        for ($i = 0, $n = \count($lines); $i <= $n - $fc; ++$i) {
            $block = implode("\n", \array_slice($lines, $i, $fc));
            if (trim($block) === $trimmed) {
                yield $block;
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function contextAware(string $content, string $find): iterable
    {
        $findLines = explode("\n", $find);
        if (\count($findLines) < 3) {
            return;
        }
        if ('' === end($findLines)) {
            array_pop($findLines);
        }

        $contentLines = explode("\n", $content);
        $firstLine = trim($findLines[0]);
        $lastLine = trim($findLines[\count($findLines) - 1]);

        for ($i = 0, $n = \count($contentLines); $i < $n; ++$i) {
            if (trim($contentLines[$i]) !== $firstLine) {
                continue;
            }
            for ($j = $i + 2; $j < $n; ++$j) {
                if (trim($contentLines[$j]) !== $lastLine) {
                    continue;
                }
                $blockLines = \array_slice($contentLines, $i, $j - $i + 1);
                if (\count($blockLines) === \count($findLines)) {
                    $matching = 0;
                    $totalNonEmpty = 0;
                    for ($k = 1, $bn = \count($blockLines) - 1; $k < $bn; ++$k) {
                        $bl = trim($blockLines[$k]);
                        $fl = trim($findLines[$k]);
                        if ('' !== $bl || '' !== $fl) {
                            ++$totalNonEmpty;
                            if ($bl === $fl) {
                                ++$matching;
                            }
                        }
                    }
                    if (0 === $totalNonEmpty || $matching / $totalNonEmpty >= 0.5) {
                        yield implode("\n", $blockLines);
                        break;
                    }
                }
                break;
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function multiOccurrence(string $content, string $find): iterable
    {
        if ('' === $find) {
            return;
        }
        $start = 0;
        while (true) {
            $index = strpos($content, $find, $start);
            if (false === $index) {
                break;
            }
            yield $find;
            $start = $index + \strlen($find);
        }
    }

    private function levenshtein(string $a, string $b): int
    {
        if ('' === $a || '' === $b) {
            return max(\strlen($a), \strlen($b));
        }

        // PHP's native levenshtein() caps at 255 bytes per argument; lines can
        // exceed that, so compute it ourselves with a rolling row.
        $lb = \strlen($b);
        $previous = range(0, $lb);
        for ($i = 1, $la = \strlen($a); $i <= $la; ++$i) {
            $current = [$i];
            for ($j = 1; $j <= $lb; ++$j) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $current[$j] = min($previous[$j] + 1, $current[$j - 1] + 1, $previous[$j - 1] + $cost);
            }
            $previous = $current;
        }

        return $previous[$lb];
    }
}
