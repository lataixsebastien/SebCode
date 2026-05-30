<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\ToolCallId;
use App\Tool\Domain\Model\ValueObject\ToolName;

/**
 * The LLM's intent to invoke a tool: identifier + name + decoded arguments.
 *
 * Arguments are kept as a raw associative array — schema validation happens
 * inside each tool's `execute()` (which may raise InvalidToolArguments).
 */
final readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public ToolCallId $id,
        public ToolName $name,
        public array $arguments = [],
    ) {
    }
}
