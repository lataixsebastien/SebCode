<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\UseCase;

use SebCode\Tool\Application\Dto\Input\ExecuteToolInput;

final readonly class ExecuteToolUseCase
{
    public function __construct(public ExecuteToolInput $input)
    {
    }
}
