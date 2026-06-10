<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Port;

use SebCode\Tool\Domain\Model\ToolDescriptor;
use SebCode\Tool\Domain\Model\ToolExecutionContext;
use SebCode\Tool\Domain\Model\ToolResult;

interface Tool
{
    public function descriptor(): ToolDescriptor;

    /**
     * @param array<string, mixed> $input
     */
    public function execute(ToolExecutionContext $context, array $input): ToolResult;
}
