<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Search file contents by regular expression, relative to the project root.
 *
 * Read-only and sandboxed exactly like {@see GlobTool}/{@see ReadTool}: a path
 * resolving outside the project root is a soft failure. Where opencode shells
 * out to ripgrep, SebCode keeps the dependency-free, pure-PHP approach already
 * used for glob — a recursive walk + PCRE per line — but mirrors opencode's
 * semantics: optional `path` scope, `include` glob filter, `.git` excluded,
 * matches grouped by file and sorted by mtime (newest first), capped at 100
 * shown with per-line truncation.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/grep.ts
 */
final class GrepTool implements Tool
{
    private const int MAX_MATCHES = 100;
    private const int MAX_LINE_LENGTH = 2000;
    /** Stop walking once this many matches are collected, to bound memory. */
    private const int SCAN_CEILING = 1000;

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('grep'),
            'Search file contents with a regular expression, relative to the project root. '
                .'Supports full regex syntax (e.g. "log.*Error", "function\\s+\\w+"). Filter files with '
                .'the include glob (e.g. "*.php", "*.{ts,tsx}") and narrow with path. Returns file paths '
                .'and line numbers with matching lines, newest files first.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'The regular expression to search for in file contents.',
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'File or directory (relative to project root) to scope the search. Defaults to the whole project.',
                    ],
                    'include' => [
                        'type' => 'string',
                        'description' => 'Glob filter for file names, e.g. "*.php" or "*.{ts,tsx}".',
                    ],
                ],
                'required' => ['pattern'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $pattern = $call->arguments['pattern'] ?? null;
        if (!\is_string($pattern) || '' === $pattern) {
            throw InvalidToolArguments::for($call->name, 'argument "pattern" must be a non-empty string');
        }

        $path = $call->arguments['path'] ?? null;
        if (null !== $path && !\is_string($path)) {
            throw InvalidToolArguments::for($call->name, 'argument "path" must be a string');
        }

        $include = $call->arguments['include'] ?? null;
        if (null !== $include && !\is_string($include)) {
            throw InvalidToolArguments::for($call->name, 'argument "include" must be a string');
        }

        $regex = "\1".$pattern."\1";
        if (false === @preg_match($regex, '')) {
            return ToolResult::failure($call->id, \sprintf('invalid regular expression: %s', $pattern));
        }

        $root = realpath($context->projectRoot);
        if (false === $root) {
            return ToolResult::failure($call->id, 'project root does not exist on disk');
        }

        $scope = $root;
        if (null !== $path && '' !== $path) {
            $resolved = realpath($root.\DIRECTORY_SEPARATOR.ltrim($path, '/\\'));
            if (false === $resolved) {
                return ToolResult::failure($call->id, \sprintf('path "%s" not found under the project root', $path));
            }
            if (!str_starts_with($resolved, $root.\DIRECTORY_SEPARATOR) && $resolved !== $root) {
                return ToolResult::failure($call->id, \sprintf('path "%s" is outside the project root', $path));
            }
            $scope = $resolved;
        }

        [$matches, $capped] = $this->search($scope, $regex, $include);

        return ToolResult::success(
            $call->id,
            $this->render($matches, $root, $capped),
            ['matches' => \count($matches), 'capped' => $capped],
        );
    }

    /**
     * @return array{0: list<array{path: string, line: int, text: string, mtime: int}>, 1: bool}
     */
    private function search(string $scope, string $regex, ?string $include): array
    {
        $files = is_dir($scope) ? $this->walk($scope) : [$scope];
        $includes = (null !== $include && '' !== $include) ? $this->expandBraces($include) : null;

        $matches = [];
        $capped = false;
        foreach ($files as $file) {
            if (null !== $includes && !$this->included(basename($file), $includes)) {
                continue;
            }

            foreach ($this->grepFile($file, $regex) as $hit) {
                $matches[] = [
                    'path' => $file,
                    'line' => $hit['line'],
                    'text' => $hit['text'],
                    'mtime' => (int) @filemtime($file),
                ];
                if (\count($matches) >= self::SCAN_CEILING) {
                    $capped = true;
                    break 2;
                }
            }
        }

        // Newest files first, then stable by path + line — opencode sorts by mtime desc.
        usort($matches, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime']
                ?: (strcmp($a['path'], $b['path'])
                ?: $a['line'] <=> $b['line']);
        });

        return [$matches, $capped];
    }

    /**
     * @return list<string>
     */
    private function walk(string $dir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        $files = [];
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if (!$file->isFile()) {
                continue;
            }
            if (str_contains(str_replace('\\', '/', $file->getPathname()), '/.git/')) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return list<array{line: int, text: string}>
     */
    private function grepFile(string $file, string $regex): array
    {
        $handle = @fopen($file, 'r');
        if (false === $handle) {
            return [];
        }

        $hits = [];
        $lineNo = 0;
        try {
            while (false !== ($raw = fgets($handle))) {
                ++$lineNo;
                if (str_contains($raw, "\0")) {
                    return []; // binary file — skip entirely
                }
                $line = rtrim($raw, "\r\n");
                if (1 === preg_match($regex, $line)) {
                    $hits[] = [
                        'line' => $lineNo,
                        'text' => \strlen($line) > self::MAX_LINE_LENGTH
                            ? substr($line, 0, self::MAX_LINE_LENGTH).'...'
                            : $line,
                    ];
                }
            }
        } finally {
            fclose($handle);
        }

        return $hits;
    }

    /**
     * @param list<array{path: string, line: int, text: string, mtime: int}> $matches
     */
    private function render(array $matches, string $root, bool $capped): string
    {
        $total = \count($matches);
        if (0 === $total) {
            return 'Found 0 matches.';
        }

        $shown = \array_slice($matches, 0, self::MAX_MATCHES);
        $truncated = $total > self::MAX_MATCHES;

        $header = \sprintf('Found %d matches%s', $total, $truncated ? \sprintf(' (showing first %d)', self::MAX_MATCHES) : '');
        $lines = [$header];

        $current = null;
        foreach ($shown as $match) {
            $relative = ltrim(substr($match['path'], \strlen($root)), '/\\');
            if ($current !== $relative) {
                $lines[] = '';
                $lines[] = $relative.':';
                $current = $relative;
            }
            $lines[] = \sprintf('  Line %d: %s', $match['line'], $match['text']);
        }

        if ($capped) {
            $lines[] = '';
            $lines[] = \sprintf('--- search stopped after %d matches ---', self::SCAN_CEILING);
        }

        return implode(\PHP_EOL, $lines);
    }

    /**
     * @param list<string> $patterns
     */
    private function included(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $name, \FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand a single level of `{a,b,c}` alternation into concrete fnmatch
     * patterns (e.g. "*.{ts,tsx}" → ["*.ts", "*.tsx"]). Plain patterns pass
     * through unchanged.
     *
     * @return list<string>
     */
    private function expandBraces(string $pattern): array
    {
        if (1 !== preg_match('/^(.*)\{([^{}]+)\}(.*)$/', $pattern, $m)) {
            return [$pattern];
        }

        $out = [];
        foreach (explode(',', $m[2]) as $option) {
            foreach ($this->expandBraces($m[1].$option.$m[3]) as $expanded) {
                $out[] = $expanded;
            }
        }

        return $out;
    }
}
