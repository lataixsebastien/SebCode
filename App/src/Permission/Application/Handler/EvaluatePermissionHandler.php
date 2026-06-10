<?php

declare(strict_types=1);

namespace SebCode\Permission\Application\Handler;

use SebCode\Permission\Application\Dto\Output\PermissionDecisionOutput;
use SebCode\Permission\Application\UseCase\EvaluatePermissionUseCase;
use SebCode\Permission\Domain\PermissionPolicy;

final readonly class EvaluatePermissionHandler
{
    public function __construct(private PermissionPolicy $permissionPolicy)
    {
    }

    public function __invoke(EvaluatePermissionUseCase $useCase): PermissionDecisionOutput
    {
        return PermissionDecisionOutput::fromDomain($this->permissionPolicy->decide(
            $useCase->input->request,
            $useCase->input->context,
        ));
    }
}
