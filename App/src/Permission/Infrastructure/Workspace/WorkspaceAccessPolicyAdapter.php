<?php

declare(strict_types=1);

namespace SebCode\Permission\Infrastructure\Workspace;

use SebCode\Permission\Domain\Port\WorkspaceAccessPolicy;
use SebCode\Workspace\Application\Dto\Input\ResolveWorkspacePathInput;
use SebCode\Workspace\Application\Handler\ResolveWorkspacePathHandler;
use SebCode\Workspace\Application\UseCase\ResolveWorkspaceUseCase;

final readonly class WorkspaceAccessPolicyAdapter implements WorkspaceAccessPolicy
{
    public function __construct(private ResolveWorkspacePathHandler $resolveWorkspacePathHandler)
    {
    }

    public function assertPathAllowed(string $workspaceRoot, string $path): string
    {
        return ($this->resolveWorkspacePathHandler)(new ResolveWorkspaceUseCase(
            new ResolveWorkspacePathInput($workspaceRoot, $path),
        ))->absolutePath;
    }
}
