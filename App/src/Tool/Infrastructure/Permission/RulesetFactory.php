<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\PermissionRule;
use App\Tool\Domain\Model\PermissionRuleset;
use App\Tool\Domain\Model\ValueObject\PermissionAction;

/**
 * Builds a {@see PermissionRuleset} from plain config rows.
 *
 * Each row is `{ type: string, pattern: string, action: string }`, mirroring
 * opencode's `fromConfig()` expansion of the `permission` config block. Wired
 * from the `tool.permission.rules` container parameter in services.yaml.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts:288 (fromConfig)
 */
final class RulesetFactory
{
    /**
     * @param list<array{type: string, pattern: string, action: string}> $rules
     */
    public static function fromConfig(array $rules): PermissionRuleset
    {
        $built = [];
        foreach ($rules as $row) {
            $built[] = new PermissionRule(
                $row['type'],
                $row['pattern'],
                PermissionAction::from($row['action']),
            );
        }

        return new PermissionRuleset($built);
    }
}
