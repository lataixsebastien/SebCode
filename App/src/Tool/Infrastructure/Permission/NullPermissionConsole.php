<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsole;

/**
 * The "no UI attached" console: never active, so the prompter never calls
 * confirm() and falls back to its configured default instead.
 *
 * Default binding for {@see PermissionConsole} and the registry's initial
 * state — used by headless/one-shot/test runs.
 */
final class NullPermissionConsole implements PermissionConsole
{
    public function isActive(): bool
    {
        return false;
    }

    public function confirm(PermissionRequest $request, string $subject): PermissionChoice
    {
        // Unreachable in practice: callers guard on isActive(). Be safe anyway.
        return PermissionChoice::Reject;
    }
}
