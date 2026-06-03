<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

/**
 * One `@@` change block inside an Update File section.
 *
 * `oldLines` are the context+removed lines to locate in the file; `newLines`
 * are the context+added lines that replace them. `isEndOfFile` anchors the
 * match at the end of the file (`*** End of File` marker).
 */
final readonly class PatchHunk
{
    /**
     * @param list<string> $oldLines
     * @param list<string> $newLines
     */
    public function __construct(
        public array $oldLines,
        public array $newLines,
        public bool $isEndOfFile = false,
    ) {
    }
}
