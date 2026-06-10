<?php

declare(strict_types=1);

namespace SebCode\Workspace\Domain;

use SebCode\Workspace\Domain\Exception\WorkspaceViolation;

final class PathNormalizer
{
    public function normalize(string $workspaceRoot, string $path): string
    {
        $workspaceRoot = $this->normalizeRoot($workspaceRoot);
        $path = $this->sanitize($path);

        if ('' === $path) {
            throw new WorkspaceViolation('Path must not be empty.');
        }

        if ($this->isHomePath($path)) {
            throw new WorkspaceViolation('Home paths are not allowed.');
        }

        if (!$this->isAbsolutePath($path)) {
            $path = $workspaceRoot.'/'.$path;
        }

        return $this->normalizeAbsolutePath($path);
    }

    public function normalizeRoot(string $workspaceRoot): string
    {
        return $this->normalizeAbsolutePath($workspaceRoot);
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = $this->sanitize($path);

        if ('' === $path) {
            throw new WorkspaceViolation('Path must not be empty.');
        }

        $prefix = '';
        if (1 === preg_match('#^[A-Za-z]:/#', $path)) {
            $prefix = strtolower(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
        }

        $segments = [];
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if ('/' === $prefix) {
            return '/'.$normalized;
        }

        if ('' !== $prefix) {
            return $prefix.('' === $normalized ? '/' : '/'.$normalized);
        }

        return $normalized;
    }

    private function sanitize(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new WorkspaceViolation('Null bytes are not allowed in paths.');
        }

        $normalized = str_replace('\\', '/', trim($path));
        $normalized = preg_replace('#/+#', '/', $normalized);

        return $normalized ?? '';
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || 1 === preg_match('#^[A-Za-z]:/#', $path);
    }

    private function isHomePath(string $path): bool
    {
        if ('~' === $path || str_starts_with($path, '~/')) {
            return true;
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if (!is_string($home) || '' === $home) {
            return false;
        }

        $home = $this->normalizeAbsolutePath($home);
        $candidate = $this->isAbsolutePath($path) ? $this->normalizeAbsolutePath($path) : $path;

        return $candidate === $home || str_starts_with($candidate, $home.'/');
    }
}
