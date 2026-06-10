<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Model;

final readonly class ToolExecutionContext
{
    public function __construct(public string $workspaceRoot)
    {
    }
}
