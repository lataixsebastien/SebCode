<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * The category of action a tool wants permission for.
 *
 * Mirrors opencode's permission identifiers (`edit`, `bash`,
 * `external_directory`). The string value is what a ruleset rule matches
 * against, so it MUST stay wire-stable.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/permission/index.ts
 */
enum PermissionType: string
{
    /** Mutating a file in the workspace (write + edit tools). */
    case Edit = 'edit';

    /** Running a shell command (shell tool). */
    case Bash = 'bash';

    /** Touching a path outside the project root. */
    case ExternalDirectory = 'external_directory';
}
