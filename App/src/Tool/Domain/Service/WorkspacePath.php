<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

use App\Tool\Domain\Exception\PathNotAllowed;

/**
 * Resolves a project path to a safe absolute path for writing.
 *
 * The caller may pass either a workspace-relative path ("src/Foo.php") or an
 * absolute path that already points inside the workspace ("/var/www/App/x");
 * eager models often emit the latter. Both are accepted; an absolute path that
 * escapes the workspace is rejected. Then, because write/edit targets may not
 * exist yet, we:
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
    public static function resolveForWrite(string $projectRoot, string $path): string
    {
        $path = trim($path);
        if ('' === $path) {
            throw PathNotAllowed::empty();
        }

        $root = realpath($projectRoot);
        if (false === $root) {
            throw PathNotAllowed::rootMissing();
        }

        // An absolute path must already live inside the workspace; reduce it to
        // the in-root remainder so it is not re-joined onto the root (which would
        // otherwise turn "/var/www/App/x" into "/var/www/App/var/www/App/x").
        $relative = self::stripAbsolutePrefix($path, $root);
        if (null === $relative || '' === $relative) {
            throw PathNotAllowed::outside($path);
        }

        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $relative)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                throw PathNotAllowed::traversal($path);
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
     * Clean workspace-relative form used as the permission pattern and for
     * display (forward slashes, no leading slash). Accepts the same relative-or-
     * absolute input as {@see resolveForWrite} so the pattern stays consistent
     * with the resolved target (an absolute in-root path collapses to its
     * remainder). Non-throwing: callers resolve first, so an escaping path never
     * reaches here.
     */
    public static function relativePattern(string $projectRoot, string $path): string
    {
        $path = trim($path);

        $root = realpath($projectRoot);
        if (false !== $root) {
            $stripped = self::stripAbsolutePrefix($path, $root);
            if (null !== $stripped) {
                $path = $stripped;
            }
        }

        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * If `$path` is absolute, return its portion inside `$root` ('' for the root
     * itself, null when it escapes the workspace). A relative path is returned
     * unchanged.
     */
    private static function stripAbsolutePrefix(string $path, string $root): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        if (!self::isAbsolute($normalized)) {
            return $path;
        }

        $rootForward = rtrim(str_replace('\\', '/', $root), '/');
        if ($normalized === $rootForward) {
            return '';
        }
        if (str_starts_with($normalized, $rootForward.'/')) {
            return substr($normalized, \strlen($rootForward) + 1);
        }

        return null;
    }

    private static function isAbsolute(string $normalized): bool
    {
        return str_starts_with($normalized, '/')           // POSIX / UNC
            || 1 === preg_match('#^[A-Za-z]:/#', $normalized); // Windows drive
    }
}
