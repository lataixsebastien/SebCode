<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\EditConflict;
use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Exception\PathNotAllowed;
use App\Tool\Domain\Exception\ToolExecutionFailed;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\PermissionGate;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;
use App\Tool\Domain\Service\EditReplacer;
use App\Tool\Domain\Service\WorkspacePath;

/**
 * Apply an exact (or fuzzily-matched) string replacement to a file.
 *
 * Mutating tool, gated through {@see PermissionGate} (type `edit`). The match
 * is resolved by {@see EditReplacer} (opencode's nine-replacer chain). An
 * empty `oldString` creates/overwrites the file with `newString`, mirroring
 * opencode's edit.ts. Match failures are soft (fed back to the LLM); a denied
 * permission is a hard failure that aborts the loop.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/edit.ts
 */
final readonly class EditTool implements Tool
{
    public function __construct(
        private PermissionGate $permission,
        private EditReplacer $replacer,
    ) {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('edit'),
            'Replace an exact string in a file under the project root. Read the file first. '
                .'oldString must match the file content (including indentation). Use replaceAll to '
                .'replace every occurrence; otherwise oldString must be unique. An empty oldString '
                .'creates or overwrites the file with newString.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'filePath' => [
                        'type' => 'string',
                        'description' => 'File path relative to the project root.',
                    ],
                    'oldString' => [
                        'type' => 'string',
                        'description' => 'Text to replace. Empty to create/overwrite the file.',
                    ],
                    'newString' => [
                        'type' => 'string',
                        'description' => 'Replacement text (must differ from oldString).',
                    ],
                    'replaceAll' => [
                        'type' => 'boolean',
                        'description' => 'Replace all occurrences of oldString (default false).',
                    ],
                ],
                'required' => ['filePath', 'oldString', 'newString'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $filePath = $call->arguments['filePath'] ?? null;
        if (!\is_string($filePath) || '' === $filePath) {
            throw InvalidToolArguments::for($call->name, 'argument "filePath" must be a non-empty string');
        }
        $oldString = $call->arguments['oldString'] ?? null;
        if (!\is_string($oldString)) {
            throw InvalidToolArguments::for($call->name, 'argument "oldString" must be a string');
        }
        $newString = $call->arguments['newString'] ?? null;
        if (!\is_string($newString)) {
            throw InvalidToolArguments::for($call->name, 'argument "newString" must be a string');
        }
        $replaceAll = $call->arguments['replaceAll'] ?? false;
        if (!\is_bool($replaceAll)) {
            throw InvalidToolArguments::for($call->name, 'argument "replaceAll" must be a boolean');
        }

        try {
            $absolute = WorkspacePath::resolveForWrite($context->projectRoot, $filePath);
        } catch (PathNotAllowed $e) {
            return ToolResult::failure($call->id, $e->getMessage());
        }
        $relative = WorkspacePath::relativePattern($filePath);

        if ('' === $oldString) {
            return $this->createFile($call, $absolute, $relative, $newString);
        }

        if (!is_file($absolute)) {
            return ToolResult::failure($call->id, \sprintf('file "%s" not found', $relative));
        }
        $content = @file_get_contents($absolute);
        if (false === $content) {
            return ToolResult::failure($call->id, \sprintf('unable to read "%s"', $relative));
        }

        $ending = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $old = $this->toEnding($oldString, $ending);
        $new = $this->toEnding($newString, $ending);

        try {
            $updated = $this->replacer->replace($content, $old, $new, $replaceAll);
        } catch (EditConflict $e) {
            return ToolResult::failure($call->id, $e->getMessage());
        }

        $this->permission->ensure(new PermissionRequest(
            PermissionType::Edit,
            [$relative],
            ['filePath' => $relative],
        ));

        if (false === @file_put_contents($absolute, $updated)) {
            throw ToolExecutionFailed::for($call->name, \sprintf('could not write "%s"', $relative));
        }

        return ToolResult::success(
            $call->id,
            \sprintf('Edited %s.', $relative),
            ['filePath' => $relative, 'replaceAll' => $replaceAll],
        );
    }

    private function createFile(ToolCall $call, string $absolute, string $relative, string $newString): ToolResult
    {
        $existed = is_file($absolute);

        $this->permission->ensure(new PermissionRequest(
            PermissionType::Edit,
            [$relative],
            ['filePath' => $relative, 'existed' => $existed],
        ));

        $directory = \dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ToolExecutionFailed::for($call->name, \sprintf('could not create directory for "%s"', $relative));
        }
        if (false === @file_put_contents($absolute, $newString)) {
            throw ToolExecutionFailed::for($call->name, \sprintf('could not write "%s"', $relative));
        }

        return ToolResult::success(
            $call->id,
            \sprintf('%s %s.', $existed ? 'Overwrote' : 'Created', $relative),
            ['filePath' => $relative, 'created' => !$existed],
        );
    }

    /**
     * Convert LF-normalised text to the file's detected line ending so the
     * search string lines up byte-for-byte with the content.
     */
    private function toEnding(string $text, string $ending): string
    {
        $lf = str_replace("\r\n", "\n", $text);

        return "\n" === $ending ? $lf : str_replace("\n", "\r\n", $lf);
    }
}
