<?php

declare(strict_types=1);

namespace SebCode\Workspace\Application\Dto\Output;

use SebCode\Workspace\Domain\Model\ValueObject\ResolvedWorkspacePath;

final readonly class ResolvedWorkspacePathOutput
{
    public function __construct(
        public string $workspaceRoot,
        public string $absolutePath,
        public string $relativePath,
    ) {
    }

    public static function fromDomain(ResolvedWorkspacePath $path): self
    {
        return new self($path->workspaceRoot, $path->absolutePath, $path->relativePath);
    }
}
