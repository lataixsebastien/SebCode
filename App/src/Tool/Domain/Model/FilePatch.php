<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

use App\Tool\Domain\Model\ValueObject\PatchOperationKind;

/**
 * One file section of a parsed apply_patch envelope.
 *
 * - Add    → `content` holds the full new file body; `hunks` empty.
 * - Update → `hunks` describe the in-place changes; `movePath` set if renamed.
 * - Delete → both empty.
 */
final readonly class FilePatch
{
    /**
     * @param list<PatchHunk> $hunks
     */
    public function __construct(
        public PatchOperationKind $kind,
        public string $path,
        public ?string $movePath = null,
        public ?string $content = null,
        public array $hunks = [],
    ) {
    }
}
