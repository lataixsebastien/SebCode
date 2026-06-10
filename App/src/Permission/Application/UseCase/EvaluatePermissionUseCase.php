<?php

declare(strict_types=1);

namespace SebCode\Permission\Application\UseCase;

use SebCode\Permission\Application\Dto\Input\EvaluatePermissionInput;

final readonly class EvaluatePermissionUseCase
{
    public function __construct(public EvaluatePermissionInput $input)
    {
    }
}
