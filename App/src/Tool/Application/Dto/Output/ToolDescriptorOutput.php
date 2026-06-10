<?php

declare(strict_types=1);

namespace SebCode\Tool\Application\Dto\Output;

use SebCode\Tool\Domain\Model\ToolDescriptor;

final readonly class ToolDescriptorOutput
{
    /**
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $category,
        public bool $safe,
        public int $cost,
        /** @var list<string> */
        public array $allowedModes,
        public int $timeoutSeconds,
        public bool $requiresReview,
        public array $inputSchema = [],
    ) {
    }

    public static function fromDomain(ToolDescriptor $descriptor): self
    {
        return new self(
            $descriptor->name,
            $descriptor->description,
            $descriptor->category,
            $descriptor->safe,
            $descriptor->cost,
            $descriptor->allowedModes,
            $descriptor->timeoutSeconds,
            $descriptor->requiresReview,
            $descriptor->inputSchema,
        );
    }
}
