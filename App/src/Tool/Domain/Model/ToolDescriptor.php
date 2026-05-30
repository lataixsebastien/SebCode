<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;

/**
 * Public description of a tool that the LLM consumes when deciding to invoke it.
 *
 * Pure data — the actual execution logic lives behind the `Tool` port.
 */
final readonly class ToolDescriptor
{
    public function __construct(
        public ToolName $name,
        public string $description,
        public JsonSchema $parameters,
    ) {
        if ('' === trim($description)) {
            throw new \InvalidArgumentException('Tool description cannot be empty.');
        }
    }
}
