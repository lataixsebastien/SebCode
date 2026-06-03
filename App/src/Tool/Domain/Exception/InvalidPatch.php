<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

/**
 * The patch text could not be parsed (bad envelope, unknown header, …).
 *
 * SOFT failure: the tool catches it and returns `ToolResult::failure()` so the
 * LLM sees what was malformed and can resend a valid patch.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/patch/index.ts
 */
final class InvalidPatch extends \RuntimeException
{
    public static function missingMarkers(): self
    {
        return new self('Invalid patch: missing "*** Begin Patch" / "*** End Patch" markers.');
    }

    public static function unknownHeader(string $line): self
    {
        return new self(\sprintf('Invalid patch: expected a file header, got "%s".', $line));
    }

    public static function empty(): self
    {
        return new self('Invalid patch: no file operations between the markers.');
    }
}
