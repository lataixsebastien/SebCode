<?php

declare(strict_types=1);

namespace SebCode\Workspace\Application\UseCase;

use SebCode\Workspace\Application\Dto\Input\ResolveWorkspacePathInput;

final readonly class ResolveWorkspaceUseCase
{
    public function __construct(public ResolveWorkspacePathInput $input)
    {
    }
}
