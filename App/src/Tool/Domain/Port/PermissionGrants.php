<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\ValueObject\PermissionType;

/**
 * Runtime-added "always allow" rules, the equivalent of opencode's `approved`
 * list grown when a user answers `always` to a prompt.
 *
 * Separate from the static {@see \App\Tool\Domain\Model\PermissionRuleset}
 * (config) because these are mutated during a session: the prompter writes a
 * grant, the gate reads it on the next matching subject so the human is not
 * asked again.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts (approved.push)
 */
interface PermissionGrants
{
    /**
     * Persist a wildcard pattern as allowed for the given permission type.
     */
    public function grant(PermissionType $type, string $pattern): void;

    /**
     * True when a prior grant's pattern matches this concrete subject.
     */
    public function isGranted(PermissionType $type, string $subject): bool;
}
