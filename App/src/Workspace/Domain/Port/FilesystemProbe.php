<?php

declare(strict_types=1);

namespace SebCode\Workspace\Domain\Port;

interface FilesystemProbe
{
    public function existingPathAtOrAbove(string $path): ?string;

    public function realPath(string $path): ?string;
}
