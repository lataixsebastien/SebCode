<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * A tool asking to perform a guarded action.
 *
 * Mirrors the payload opencode tools pass to `ctx.ask({ permission,
 * patterns, metadata })`. Each pattern is a concrete subject the gate
 * evaluates against the ruleset — e.g. a project-relative file path for an
 * `edit`, or a command prefix for a `bash`.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/write.ts (ctx.ask)
 */
final readonly class PermissionRequest
{
    /**
     * @var list<string>
     */
    public array $patterns;

    /**
     * @var array<string, mixed>
     */
    public array $metadata;

    /**
     * @param list<string> $patterns concrete subjects to authorize (≥1, non-empty strings)
     * @param array<string, mixed> $metadata free-form context for the prompter (e.g. a diff)
     */
    public function __construct(
        public PermissionType $type,
        array $patterns,
        array $metadata = [],
    ) {
        if ([] === $patterns) {
            throw new \InvalidArgumentException('PermissionRequest requires at least one pattern.');
        }
        foreach ($patterns as $pattern) {
            if ('' === $pattern) {
                throw new \InvalidArgumentException('PermissionRequest patterns must be non-empty strings.');
            }
        }

        $this->patterns = $patterns;
        $this->metadata = $metadata;
    }
}
