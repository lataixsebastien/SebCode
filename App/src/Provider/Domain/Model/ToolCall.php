<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain\Model;

final readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Tool call name must not be empty.');
        }
    }
}
