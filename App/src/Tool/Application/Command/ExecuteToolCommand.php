<?php

declare(strict_types=1);

namespace App\Tool\Application\Command;

use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Port\ToolExecutionContext;

final readonly class ExecuteToolCommand
{
    public function __construct(
        public ToolCall $call,
        public ToolExecutionContext $context,
    ) {
    }
}
