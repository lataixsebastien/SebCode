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
 * Find files matching a glob pattern, relative to the project root.
 *
 * Read-only, sandboxed: any path that resolves outside the project root
 * is rejected as a soft failure (the LLM sees the refusal and may retry).
 *
 * The pattern is interpreted by PHP's native glob() with GLOB_BRACE so
 * patterns like "App/src/**\/*.{php,yaml}" are supported. For recursive
 * matching with **\/ we expand via SplFileInfo iteration because PHP's
 * native glob() doesn't understand `**`.
 */
final class GlobTool implements Tool
{
    private const int DEFAULT_LIMIT = 100;
    private const int MAX_LIMIT = 500;

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('glob'),
            'Find files matching a glob pattern relative to the project root. '
                .'Supports `**` for recursive matching and `{a,b}` brace expansion. '
                .'Returns absolute paths. Use to list directories or explore project structure.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'Glob pattern relative to project root, e.g. "App/src/**/*.php".',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => self::MAX_LIMIT,
                        'description' => 'Maximum number of paths to return (default '.self::DEFAULT_LIMIT.').',
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

        $limit = $call->arguments['limit'] ?? self::DEFAULT_LIMIT;
        if (!\is_int($limit) || $limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidToolArguments::for($call->name, \sprintf('argument "limit" must be an integer in [1, %d]', self::MAX_LIMIT));
        }

        $root = realpath($context->projectRoot);
        if (false === $root) {
            return ToolResult::failure($call->id, 'project root does not exist on disk');
        }

        $matches = $this->scan($root, $pattern);
        $matches = array_values(array_filter(
            $matches,
            static function (string $path) use ($root): bool {
                // Resolve symlinks and `..` segments before checking the prefix.
                // A lexical str_starts_with check on the pre-canonical path
                // would accept "/root/../escape" — realpath() rejects that.
                $resolved = realpath($path);

                return false !== $resolved
                    && (str_starts_with($resolved, $root.\DIRECTORY_SEPARATOR) || $resolved === $root);
            },
        ));

        $total = \count($matches);
        $shown = \array_slice($matches, 0, $limit);

        $header = \sprintf('(%d matched, showing first %d)', $total, \count($shown));
        $body = $header.\PHP_EOL.implode(\PHP_EOL, $shown);
        if ($total > $limit) {
            $body .= \PHP_EOL.'--- truncated ---';
        }

        return ToolResult::success($call->id, $body, ['matched' => $total, 'returned' => \count($shown)]);
    }

    /**
     * @return list<string>
     */
    private function scan(string $root, string $pattern): array
    {
        $absolutePattern = $root.\DIRECTORY_SEPARATOR.ltrim($pattern, '/\\');

        if (!str_contains($pattern, '**')) {
            // GLOB_BRACE is not available on every platform (BSD/musl/Alpine).
            $flags = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
            $raw = glob($absolutePattern, $flags);

            return false === $raw ? [] : $raw;
        }

        // Recursive matching: split on the first `**` segment and walk.
        [$prefix, $suffix] = explode('**', $absolutePattern, 2);
        $prefix = rtrim($prefix, '/\\');
        if ('' === $prefix || !is_dir($prefix)) {
            return [];
        }
        $suffix = ltrim($suffix, '/\\');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($prefix, \FilesystemIterator::SKIP_DOTS),
        );
        $matches = [];
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if (!$file->isFile()) {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), \strlen($prefix)), '/\\');
            // Do NOT pass FNM_PATHNAME — we want `*.php` to match across nested
            // directories (that's the whole point of the `**` prefix split).
            if ('' === $suffix || fnmatch($suffix, $relative, \FNM_CASEFOLD)) {
                $matches[] = $file->getPathname();
            }
        }
        sort($matches);

        return $matches;
    }
}
