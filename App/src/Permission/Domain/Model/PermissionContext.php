<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Model;

final readonly class PermissionContext
{
    public function __construct(
        public string $workspaceRoot,
        public string $mode = 'PATCH',
    ) {
    }
}
