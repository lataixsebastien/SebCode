<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidPatch;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\PatchApplyFailed;
use App\Tool\Domain\Exception\PathNotAllowed;
use App\Tool\Domain\Exception\ToolExecutionFailed;
use App\Tool\Domain\Model\FilePatch;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\PatchOperationKind;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\PermissionGate;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Domain\Service\PatchApplier;
use App\Tool\Domain\Service\PatchParser;
use App\Tool\Domain\Service\WorkspacePath;

/**
 * Apply a multi-file patch in the OpenAI apply_patch envelope.
 *
 * Mutating tool, gated through {@see PermissionGate} (type `edit`, once for the
 * whole patch) and confined by {@see WorkspacePath}. It is **all-or-nothing**:
 * the patch is parsed and every change computed in memory first, so a hunk that
 * fails to apply (or a missing/escaping path) is a soft failure that writes
 * nothing. A denied permission is a hard failure that aborts the loop.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/apply_patch.ts
 */
final readonly class ApplyPatchTool implements Tool
{
    public function __construct(
        private PermissionGate $permission,
        private PatchParser $parser,
        private PatchApplier $applier,
    ) {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('apply_patch'),
            'Apply a multi-file patch in the apply_patch envelope. Use it to add, update, delete or '
                ."move several files in one call. Format:\n"
                ."*** Begin Patch\n*** Add File: path        (each new line prefixed with +)\n"
                .'*** Update File: path     (optional "*** Move to: newpath", then @@ hunks with '
                ." context, -removed, +added lines)\n*** Delete File: path\n*** End Patch",
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'patchText' => [
                        'type' => 'string',
                        'description' => 'The full patch text (the *** Begin Patch … *** End Patch envelope).',
                    ],
                ],
                'required' => ['patchText'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $patchText = $call->arguments['patchText'] ?? null;
        if (!\is_string($patchText) || '' === trim($patchText)) {
            throw InvalidToolArguments::for($call->name, 'argument "patchText" must be a non-empty string');
        }

        try {
            $patches = $this->parser->parse($patchText);
        } catch (InvalidPatch $e) {
            return ToolResult::failure($call->id, $e->getMessage());
        }

        // Phase 1 — validate every operation and compute results in memory.
        try {
            $plan = $this->plan($patches, $context->projectRoot);
        } catch (PathNotAllowed|PatchApplyFailed $e) {
            return ToolResult::failure($call->id, $e->getMessage());
        }

        // Phase 2 — one permission ask for the whole patch (hard failure if denied).
        $this->permission->ensure(new PermissionRequest(
            PermissionType::Edit,
            $plan['patterns'],
            ['files' => implode(', ', $plan['patterns'])],
            ['*'],
        ));

        // Phase 3 — commit to disk.
        foreach ($plan['writes'] as [$absolute, $content]) {
            $this->writeFile($call->name, $absolute, $content);
        }
        foreach ($plan['deletes'] as $absolute) {
            if (is_file($absolute) && !@unlink($absolute)) {
                throw ToolExecutionFailed::for($call->name, \sprintf('could not delete "%s"', $absolute));
            }
        }

        return ToolResult::success(
            $call->id,
            "Applied patch:\n".implode("\n", $plan['summary']),
            ['files' => $plan['patterns']],
        );
    }

    /**
     * @param list<FilePatch> $patches
     *
     * @return array{patterns: list<string>, summary: list<string>, writes: list<array{0: string, 1: string}>, deletes: list<string>}
     *
     * @throws PathNotAllowed|PatchApplyFailed
     */
    private function plan(array $patches, string $projectRoot): array
    {
        $patterns = [];
        $summary = [];
        $writes = [];
        $deletes = [];

        foreach ($patches as $patch) {
            $absolute = WorkspacePath::resolveForWrite($projectRoot, $patch->path);
            $relative = WorkspacePath::relativePattern($patch->path);
            $patterns[] = $relative;

            switch ($patch->kind) {
                case PatchOperationKind::Add:
                    $writes[] = [$absolute, $this->withTrailingNewline($patch->content ?? '')];
                    $summary[] = 'A '.$relative;
                    break;

                case PatchOperationKind::Delete:
                    if (!is_file($absolute)) {
                        throw PatchApplyFailed::fileNotFound($relative);
                    }
                    $deletes[] = $absolute;
                    $summary[] = 'D '.$relative;
                    break;

                case PatchOperationKind::Update:
                    if (!is_file($absolute)) {
                        throw PatchApplyFailed::fileNotFound($relative);
                    }
                    $newContent = $this->applier->apply((string) file_get_contents($absolute), $patch->hunks, $relative);

                    if (null !== $patch->movePath) {
                        $moveAbsolute = WorkspacePath::resolveForWrite($projectRoot, $patch->movePath);
                        $moveRelative = WorkspacePath::relativePattern($patch->movePath);
                        $patterns[] = $moveRelative;
                        $writes[] = [$moveAbsolute, $newContent];
                        $deletes[] = $absolute;
                        $summary[] = \sprintf('M %s -> %s', $relative, $moveRelative);
                    } else {
                        $writes[] = [$absolute, $newContent];
                        $summary[] = 'M '.$relative;
                    }
                    break;
            }
        }

        return ['patterns' => $patterns, 'summary' => $summary, 'writes' => $writes, 'deletes' => $deletes];
    }

    private function writeFile(ToolName $tool, string $absolute, string $content): void
    {
        $directory = \dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ToolExecutionFailed::for($tool, \sprintf('could not create directory for "%s"', $absolute));
        }
        if (false === @file_put_contents($absolute, $content)) {
            throw ToolExecutionFailed::for($tool, \sprintf('could not write "%s"', $absolute));
        }
    }

    private function withTrailingNewline(string $content): string
    {
        if ('' === $content || str_ends_with($content, "\n")) {
            return $content;
        }

        return $content."\n";
    }
}
