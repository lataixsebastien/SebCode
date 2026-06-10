<?php

declare(strict_types=1);

namespace SebCode\Workspace\Infrastructure\Filesystem;

use SebCode\Workspace\Domain\Port\FilesystemProbe;

final class NativeFilesystemProbe implements FilesystemProbe
{
    public function existingPathAtOrAbove(string $path): ?string
    {
        $existingPath = $path;
        while (!file_exists($existingPath)) {
            $parent = dirname($existingPath);
            if ($parent === $existingPath) {
                return null;
            }

            $existingPath = $parent;
        }

        return $existingPath;
    }

    public function realPath(string $path): ?string
    {
        $realPath = realpath($path);

        return false === $realPath ? null : $realPath;
    }
}
