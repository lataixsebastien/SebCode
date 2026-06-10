<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\Dto\Output;

use SebCode\Provider\Domain\Model\ToolCall;

final readonly class ToolCallOutput
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {
    }

    public static function fromDomain(ToolCall $toolCall): self
    {
        return new self($toolCall->name, $toolCall->arguments);
    }
}
