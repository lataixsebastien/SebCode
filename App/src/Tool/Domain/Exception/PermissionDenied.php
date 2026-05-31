<?php

declare(strict_types=1);

namespace App\Tool\Domain\Exception;

use App\Tool\Domain\Model\ValueObject\PermissionType;

/**
 * A guarded action was refused — by an explicit `deny` rule or by the
 * prompter. This is a HARD failure: it propagates out of the tool and
 * aborts the agent loop (it is NOT turned into a soft `ToolResult::failure`),
 * mirroring opencode's `DeniedError` / `RejectedError`.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts (DeniedError)
 */
final class PermissionDenied extends \RuntimeException
{
    public static function for(PermissionType $type, string $subject): self
    {
        return new self(\sprintf(
            'Permission denied for "%s" on "%s".',
            $type->value,
            $subject,
        ));
    }
}
