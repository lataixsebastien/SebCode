<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

/**
 * A write/edit target path is empty, escapes the project root, or its
 * ancestry resolves outside it. SOFT failure: surfaced as
 * `ToolResult::failure()` so the LLM can correct the path.
 */
final class PathNotAllowed extends \RuntimeException
{
    public static function empty(): self
    {
        return new self('filePath must be a non-empty path relative to the project root.');
    }

    public static function rootMissing(): self
    {
        return new self('project root does not exist on disk.');
    }

    public static function traversal(string $path): self
    {
        return new self(\sprintf('path "%s" must not contain ".." segments.', $path));
    }

    public static function outside(string $path): self
    {
        return new self(\sprintf('path "%s" is outside the project root.', $path));
    }
}
