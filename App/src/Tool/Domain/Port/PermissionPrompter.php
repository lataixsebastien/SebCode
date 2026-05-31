<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;

/**
 * Resolves an undecided (`Ask`) permission into a final decision.
 *
 * Abstracts the interaction channel: a non-interactive default in one-shot
 * runs, an interactive CLI/TUI prompt later. Implementations MUST return a
 * terminal action — only {@see PermissionAction::Allow} or
 * {@see PermissionAction::Deny}, never {@see PermissionAction::Ask}.
 */
interface PermissionPrompter
{
    public function prompt(PermissionRequest $request, string $subject): PermissionAction;
}
