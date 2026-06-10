<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\Dto\Output;

use SebCode\Tool\Domain\Model\ToolResult;

final readonly class ToolResultOutput
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $success,
        public string $output,
        public array $metadata = [],
    ) {
    }

    public static function fromDomain(ToolResult $result): self
    {
        return new self($result->success, $result->output, $result->metadata);
    }
}
