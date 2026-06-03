<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

/**
 * A parsed patch could not be applied (context not found, target missing, …).
 *
 * SOFT failure: surfaced to the LLM via `ToolResult::failure()` so it can
 * re-read the file and resend an accurate patch.
 */
final class PatchApplyFailed extends \RuntimeException
{
    public static function contextNotFound(string $path): self
    {
        return new self(\sprintf('Could not locate the patch context in "%s". Re-read the file and adjust the hunk.', $path));
    }

    public static function fileNotFound(string $path): self
    {
        return new self(\sprintf('Cannot update or delete "%s": file not found.', $path));
    }
}
