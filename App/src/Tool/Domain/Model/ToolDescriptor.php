<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Model;

final readonly class ToolDescriptor
{
    /**
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $category = 'general',
        public bool $safe = false,
        public int $cost = 1,
        /** @var list<string> */
        public array $allowedModes = ['READ_ONLY', 'PATCH', 'EXECUTE', 'AUTO', 'REVIEW'],
        public int $timeoutSeconds = 30,
        public bool $requiresReview = true,
        public array $inputSchema = [],
    ) {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Tool name must not be empty.');
        }

        if ('' === trim($description)) {
            throw new \InvalidArgumentException('Tool description must not be empty.');
        }

        if ('' === trim($category)) {
            throw new \InvalidArgumentException('Tool category must not be empty.');
        }

        if ($cost < 0) {
            throw new \InvalidArgumentException('Tool cost must be zero or greater.');
        }

        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('Tool timeout must be one second or greater.');
        }
    }

    public function isAvailableForMode(string $mode): bool
    {
        return in_array($mode, $this->allowedModes, true);
    }
}
