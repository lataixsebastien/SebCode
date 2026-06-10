<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\Dto\Input;

final readonly class ExecuteToolInput
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public string $toolName,
        public string $workspaceRoot,
        public array $input,
    ) {
    }
}
