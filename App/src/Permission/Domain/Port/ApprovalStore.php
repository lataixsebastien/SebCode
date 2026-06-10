<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Port;

use SebCode\Permission\Domain\Model\PermissionContext;
use SebCode\Permission\Domain\Model\PermissionRequest;

interface ApprovalStore
{
    public function hasApproval(PermissionRequest $request, PermissionContext $context): bool;
}
