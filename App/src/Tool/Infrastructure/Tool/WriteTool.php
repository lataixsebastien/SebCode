<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

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
use App\Tool\Domain\Service\WorkspacePath;

/**
 * Write (create or overwrite) a file under the project root.
 *
 * Mutating tool: confined to the workspace by {@see WorkspacePath} and gated
 * through {@see PermissionGate} (permission type `edit`) before touching disk —
 * mirroring opencode's write.ts. A denied permission propagates as a hard
 * failure (PermissionDenied) and aborts the agent loop; bad paths are soft
 * failures fed back to the LLM.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/write.ts
 */
final readonly class WriteTool implements Tool
{
    public function __construct(private PermissionGate $permission)
    {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('write'),
            'Write a file under the project root, creating parent directories as needed. '
                .'Overwrites an existing file. For an existing file, read it first. '
                .'Prefer editing existing files over creating new ones.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'filePath' => [
                        'type' => 'string',
                        'description' => 'File path relative to the project root.',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Full content to write to the file.',
                    ],
                ],
                'required' => ['filePath', 'content'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $filePath = $call->arguments['filePath'] ?? null;
        if (!\is_string($filePath) || '' === $filePath) {
            throw InvalidToolArguments::for($call->name, 'argument "filePath" must be a non-empty string');
        }
        $content = $call->arguments['content'] ?? null;
        if (!\is_string($content)) {
            throw InvalidToolArguments::for($call->name, 'argument "content" must be a string');
        }

        try {
            $absolute = WorkspacePath::resolveForWrite($context->projectRoot, $filePath);
        } catch (PathNotAllowed $e) {
            return ToolResult::failure($call->id, $e->getMessage());
        }

        $relative = WorkspacePath::relativePattern($context->projectRoot, $filePath);
        $existed = is_file($absolute);

        // Hard failure if denied — propagates and aborts the loop (opencode parity).
        $this->permission->ensure(new PermissionRequest(
            PermissionType::Edit,
            [$relative],
            ['filePath' => $relative, 'existed' => $existed],
        ));

        $directory = \dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ToolExecutionFailed::for($call->name, \sprintf('could not create directory for "%s"', $relative));
        }

        $bytes = @file_put_contents($absolute, $content);
        if (false === $bytes) {
            throw ToolExecutionFailed::for($call->name, \sprintf('could not write "%s"', $relative));
        }

        return ToolResult::success(
            $call->id,
            \sprintf('Wrote %s (%d bytes).', $relative, $bytes),
            ['filePath' => $relative, 'bytes' => $bytes, 'created' => !$existed],
        );
    }
}
