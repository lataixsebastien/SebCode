<?php

declare(strict_types=1);

namespace SebCode\Workspace\Application\Dto\Input;

final readonly class ResolveWorkspacePathInput
{
    public function __construct(
        public string $workspaceRoot,
        public string $path,
    ) {
    }
}
