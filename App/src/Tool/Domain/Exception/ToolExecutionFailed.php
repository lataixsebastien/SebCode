<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

use App\Tool\Domain\Model\ValueObject\ToolName;

/**
 * Hard failure inside a tool — infrastructure broken, sandbox violated in
 * an unrecoverable way, etc. Soft failures (bad arguments, file not found)
 * should be surfaced as `ToolResult::failure()` so the loop can continue.
 */
final class ToolExecutionFailed extends \RuntimeException
{
    public static function for(ToolName $name, string $reason, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Tool "%s" failed: %s', $name->value, $reason), 0, $previous);
    }
}
