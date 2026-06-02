<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Exception\PermissionDenied;
use App\Tool\Domain\Model\PermissionRuleset;
use App\Tool\Domain\Model\ValueObject\PermissionAction;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionGate;
use App\Tool\Domain\Port\PermissionGrants;
use App\Tool\Domain\Port\PermissionPrompter;

/**
 * Ruleset-driven gate, faithful to opencode's `Permission.ask`.
 *
 * For each pattern in the request:
 *   - already granted at runtime (a prior "always") → keep going.
 *   - `Deny`  → refuse immediately (PermissionDenied).
 *   - `Allow` → keep going.
 *   - `Ask`   → defer to the prompter; only its `Allow` lets us continue.
 *
 * The runtime grants are consulted before the static ruleset, mirroring how
 * opencode evaluates against its `approved` list grown by "always" replies.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts:171
 */
final readonly class RulesetPermissionGate implements PermissionGate
{
    public function __construct(
        private PermissionRuleset $ruleset,
        private PermissionPrompter $prompter,
        private PermissionGrants $grants,
    ) {
    }

    public function ensure(PermissionRequest $request): void
    {
        foreach ($request->patterns as $subject) {
            if ($this->grants->isGranted($request->type, $subject)) {
                continue;
            }

            $action = $this->ruleset->evaluate($request->type, $subject);

            if (PermissionAction::Allow === $action) {
                continue;
            }

            if (PermissionAction::Ask === $action) {
                $action = $this->prompter->prompt($request, $subject);
            }

            if (PermissionAction::Allow !== $action) {
                throw PermissionDenied::for($request->type, $subject);
            }
        }
    }
}
