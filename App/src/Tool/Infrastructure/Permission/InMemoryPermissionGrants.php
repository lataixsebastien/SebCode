<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Permission;

use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Port\PermissionGrants;
use App\Tool\Domain\Service\WildcardMatcher;

/**
 * Session-lifetime "always allow" store, kept in memory.
 *
 * Shared (singleton) so a grant added while answering one prompt is visible to
 * the gate on the next matching subject. Matching reuses {@see WildcardMatcher}
 * for parity with how the static ruleset matches subjects.
 */
final class InMemoryPermissionGrants implements PermissionGrants
{
    /**
     * @var list<array{type: PermissionType, pattern: string}>
     */
    private array $grants = [];

    public function grant(PermissionType $type, string $pattern): void
    {
        foreach ($this->grants as $grant) {
            if ($grant['type'] === $type && $grant['pattern'] === $pattern) {
                return;
            }
        }

        $this->grants[] = ['type' => $type, 'pattern' => $pattern];
    }

    public function isGranted(PermissionType $type, string $subject): bool
    {
        foreach ($this->grants as $grant) {
            if ($grant['type'] === $type && WildcardMatcher::matches($subject, $grant['pattern'])) {
                return true;
            }
        }

        return false;
    }
}
