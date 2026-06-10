<?php

declare(strict_types=1);

namespace SebCode\Permission\Infrastructure\Repository;

use SebCode\Permission\Domain\Model\PermissionContext;
use SebCode\Permission\Domain\Model\PermissionRequest;
use SebCode\Permission\Domain\Port\ApprovalStore;

final class InMemoryApprovalStore implements ApprovalStore
{
    public function hasApproval(PermissionRequest $request, PermissionContext $context): bool
    {
        return false;
    }
}
