<?php

declare(strict_types=1);

namespace SebCode\Workspace\Application\Handler;

use SebCode\Workspace\Application\Dto\Output\ResolvedWorkspacePathOutput;
use SebCode\Workspace\Application\UseCase\ResolveWorkspaceUseCase;
use SebCode\Workspace\Domain\WorkspaceGuard;

final readonly class ResolveWorkspacePathHandler
{
    public function __construct(private WorkspaceGuard $workspaceGuard)
    {
    }

    public function __invoke(ResolveWorkspaceUseCase $useCase): ResolvedWorkspacePathOutput
    {
        return ResolvedWorkspacePathOutput::fromDomain($this->workspaceGuard->assertPathAllowed(
            $useCase->input->workspaceRoot,
            $useCase->input->path,
        ));
    }
}
