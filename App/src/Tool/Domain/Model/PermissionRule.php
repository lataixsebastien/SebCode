<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\PermissionAction;

/**
 * One row of a permission ruleset.
 *
 * Both `typePattern` and `subjectPattern` are wildcard globs (fnmatch
 * syntax). A rule fires when the request's type AND subject both match.
 * Mirrors opencode's `{ permission, pattern, action }` rule shape.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts (Rule)
 */
final readonly class PermissionRule
{
    public function __construct(
        public string $typePattern,
        public string $subjectPattern,
        public PermissionAction $action,
    ) {
        if ('' === $typePattern || '' === $subjectPattern) {
            throw new \InvalidArgumentException('PermissionRule patterns must be non-empty.');
        }
    }
}
