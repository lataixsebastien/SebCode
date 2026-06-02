<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

/**
 * The `oldString` of an edit could not be applied unambiguously.
 *
 * A SOFT failure: the tool catches it and returns `ToolResult::failure()`
 * so the LLM sees the reason and can retry with better context. Messages
 * mirror opencode's edit.ts error strings.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/edit.ts:674 (replace)
 */
final class EditConflict extends \RuntimeException
{
    public static function noChange(): self
    {
        return new self('No changes to apply: oldString and newString are identical.');
    }

    public static function notFound(): self
    {
        return new self(
            'Could not find oldString in the file. It must match exactly, '
            .'including whitespace, indentation, and line endings.',
        );
    }

    public static function multipleMatches(): self
    {
        return new self(
            'Found multiple matches for oldString. '
            .'Provide more surrounding context to make the match unique.',
        );
    }
}
