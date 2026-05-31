<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * The decision a ruleset (or a prompter) yields for a permission request.
 *
 * - `Allow` — proceed silently.
 * - `Deny`  — refuse hard (raise PermissionDenied).
 * - `Ask`   — undecided by config; defer to a human/prompter.
 *
 * A prompter only ever returns `Allow` or `Deny` — `Ask` is the ruleset's
 * "no opinion" default, never a final answer.
 */
enum PermissionAction: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Ask = 'ask';
}
