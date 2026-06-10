<?php

declare(strict_types=1);

namespace SebCode\Workspace\Domain;

use SebCode\Workspace\Domain\Exception\WorkspaceViolation;
use SebCode\Workspace\Domain\Model\ValueObject\ResolvedWorkspacePath;
use SebCode\Workspace\Domain\Port\FilesystemProbe;

final readonly class WorkspaceGuard
{
    public function __construct(
        private PathNormalizer $pathNormalizer,
        private IgnoreMatcher $ignoreMatcher,
        private FilesystemProbe $filesystemProbe,
    ) {
    }

    public function assertPathAllowed(string $workspaceRoot, string $path): ResolvedWorkspacePath
    {
        $root = $this->pathNormalizer->normalizeRoot($workspaceRoot);
        $candidate = $this->pathNormalizer->normalize($root, $path);

        if (!$this->isInside($root, $candidate)) {
            throw new WorkspaceViolation(sprintf('Path "%s" is outside the workspace.', $path));
        }

        $relativePath = $this->relativePath($root, $candidate);
        $matchedPattern = $this->ignoreMatcher->matchedPattern($relativePath);
        if (null !== $matchedPattern) {
            throw new WorkspaceViolation(sprintf('Path "%s" is blocked by pattern "%s".', $path, $matchedPattern));
        }

        $this->assertExistingTargetIsInsideWorkspace($root, $candidate);

        return new ResolvedWorkspacePath($root, $candidate, $relativePath);
    }

    private function assertExistingTargetIsInsideWorkspace(string $root, string $candidate): void
    {
        $existingPath = $this->filesystemProbe->existingPathAtOrAbove($candidate);
        if (null === $existingPath) {
            return;
        }

        $realRoot = $this->filesystemProbe->realPath($root);
        $realTarget = $this->filesystemProbe->realPath($existingPath);
        if (null === $realRoot || null === $realTarget) {
            return;
        }

        $realRoot = str_replace('\\', '/', $realRoot);
        $realTarget = str_replace('\\', '/', $realTarget);

        if (!$this->isInside($realRoot, $realTarget)) {
            throw new WorkspaceViolation('Path resolves outside the workspace.');
        }
    }

    private function isInside(string $root, string $candidate): bool
    {
        $root = rtrim($root, '/');
        $candidate = rtrim($candidate, '/');

        if ($this->isWindowsPath($root) || $this->isWindowsPath($candidate)) {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }

        return $candidate === $root || str_starts_with($candidate, $root.'/');
    }

    private function relativePath(string $root, string $candidate): string
    {
        $root = rtrim($root, '/');
        if ($candidate === $root) {
            return '';
        }

        return ltrim(substr($candidate, strlen($root)), '/');
    }

    private function isWindowsPath(string $path): bool
    {
        return 1 === preg_match('#^[A-Za-z]:/#', $path);
    }
}
