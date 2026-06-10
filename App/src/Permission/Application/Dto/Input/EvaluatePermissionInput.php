<?php

declare(strict_types=1);

namespace SebCode\Permission\Application\Dto\Input;

use SebCode\Permission\Domain\Model\PermissionContext;
use SebCode\Permission\Domain\Model\PermissionRequest;

final readonly class EvaluatePermissionInput
{
    public function __construct(
        public PermissionRequest $request,
        public PermissionContext $context,
    ) {
    }
}
