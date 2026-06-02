<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * What a human answered to an interactive permission prompt.
 *
 * Mirrors opencode's `Reply` literals (`once` | `always` | `reject`):
 *
 *   - {@see AllowOnce}   — authorize this single call.
 *   - {@see AllowAlways} — authorize and persist the request's `always`
 *                          patterns for the rest of the session.
 *   - {@see Reject}      — refuse (the gate raises PermissionDenied).
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts
 */
enum PermissionChoice: string
{
    case AllowOnce = 'once';
    case AllowAlways = 'always';
    case Reject = 'reject';
}
