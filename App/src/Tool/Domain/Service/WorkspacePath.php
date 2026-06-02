<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

use App\Tool\Domain\Exception\PathNotAllowed;

/**
 * Resolves a project-relative path to a safe absolute path for writing.
 *
 * Unlike read/glob (which can `realpath()` an existing file), write/edit
 * targets may not exist yet, so we:
 *   1. reject any `..` segment (no lexical escape),
 *   2. join the cleaned segments onto the canonical project root,
 *   3. `realpath()` the deepest EXISTING ancestor and confirm it is still
 *      inside the root (defends against a symlinked parent escaping).
 *
 * Pure stdlib, so it stays Domain-clean.
 */
final class WorkspacePath
{
    /**
     * @throws PathNotAllowed if the path is empty, traverses up, or escapes the root
     */
    public static function resolveForWrite(string $projectRoot, string $relative): string
    {
        $relative = trim($relative);
        if ('' === $relative) {
            throw PathNotAllowed::empty();
        }

        $root = realpath($projectRoot);
        if (false === $root) {
            throw PathNotAllowed::rootMissing();
        }

        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $relative)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                throw PathNotAllowed::traversal($relative);
            }
            $segments[] = $segment;
        }
        if ([] === $segments) {
            throw PathNotAllowed::empty();
        }

        $absolute = $root.\DIRECTORY_SEPARATOR.implode(\DIRECTORY_SEPARATOR, $segments);

        $ancestor = \dirname($absolute);
        while (!is_dir($ancestor)) {
            $parent = \dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }

        $realAncestor = realpath($ancestor);
        if (false === $realAncestor
            || (!str_starts_with($realAncestor, $root.\DIRECTORY_SEPARATOR) && $realAncestor !== $root)
        ) {
            throw PathNotAllowed::outside($relative);
        }

        return $absolute;
    }

    /**
     * Clean project-relative form used as the permission pattern (forward
     * slashes, no leading slash, `.`/`..`-free is the caller's concern).
     */
    public static function relativePattern(string $relative): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', trim($relative))) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
