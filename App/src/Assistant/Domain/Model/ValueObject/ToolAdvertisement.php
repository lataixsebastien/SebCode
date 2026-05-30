<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model\ValueObject;

/**
 * What the LLM sees about a tool: its name, a free-form description, and the
 * JSON Schema describing its arguments.
 *
 * This is a deliberate mirror of the Tool context's `ToolDescriptor` — kept
 * here in `Assistant\Domain` so the Assistant Domain does not depend on
 * `Tool\Domain` (see ADR-0004). The conversion is done by the
 * `AssistantToolGatewayAdapter` in Infrastructure.
 *
 * @phpstan-type JsonSchemaArray array{type: string, properties?: array<string, mixed>, required?: list<string>, ...}
 */
final readonly class ToolAdvertisement
{
    /**
     * @param JsonSchemaArray $parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {
        if ('' === $name) {
            throw new \InvalidArgumentException('ToolAdvertisement name cannot be empty.');
        }
        if ('' === trim($description)) {
            throw new \InvalidArgumentException('ToolAdvertisement description cannot be empty.');
        }
    }
}
