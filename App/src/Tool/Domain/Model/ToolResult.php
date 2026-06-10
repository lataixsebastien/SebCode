<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Model;

final readonly class ToolResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        public bool $success,
        public string $output,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function success(string $output, array $metadata = []): self
    {
        return new self(true, $output, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function failure(string $output, array $metadata = []): self
    {
        return new self(false, $output, $metadata);
    }
}
