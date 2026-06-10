<?php

declare(strict_types=1);

namespace SebCode\Workspace\Domain\Model\ValueObject;

final readonly class ResolvedWorkspacePath
{
    public function __construct(
        public string $workspaceRoot,
        public string $absolutePath,
        public string $relativePath,
    ) {
    }
}
