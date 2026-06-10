<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Port;

interface WorkspaceAccessPolicy
{
    public function assertPathAllowed(string $workspaceRoot, string $path): string;
}
