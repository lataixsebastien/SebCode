<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsoleRegistry;
use App\Tool\Domain\Port\PermissionGrants;
use App\Tool\Domain\Port\PermissionPrompter;

/**
 * Resolves an `Ask` by prompting the human through the live console.
 *
 * When a UI is attached it asks once/always/reject and maps the answer to a
 * terminal action; "always" first persists the request's `always` patterns
 * into {@see PermissionGrants} so the same family is not asked again. When no
 * UI is attached (headless/one-shot) it defers to the configured default
 * prompter — keeping non-interactive runs safe and deterministic.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts (reply)
 */
final readonly class InteractivePermissionPrompter implements PermissionPrompter
{
    public function __construct(
        private PermissionConsoleRegistry $registry,
        private PermissionGrants $grants,
        private PermissionPrompter $fallback,
    ) {
    }

    public function prompt(PermissionRequest $request, string $subject): PermissionAction
    {
        $console = $this->registry->current();
        if (!$console->isActive()) {
            return $this->fallback->prompt($request, $subject);
        }

        return match ($console->confirm($request, $subject)) {
            PermissionChoice::AllowOnce => PermissionAction::Allow,
            PermissionChoice::AllowAlways => $this->remember($request),
            PermissionChoice::Reject => PermissionAction::Deny,
        };
    }

    private function remember(PermissionRequest $request): PermissionAction
    {
        foreach ($request->always as $pattern) {
            $this->grants->grant($request->type, $pattern);
        }

        return PermissionAction::Allow;
    }
}
