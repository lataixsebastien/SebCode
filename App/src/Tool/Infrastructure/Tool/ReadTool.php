<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Read a file from the project root, returning a `cat -n` style numbered view.
 *
 * Read-only, sandboxed by realpath() against the project root. Soft-failures
 * (file missing, path outside root, not a regular file) flow back as
 * `ToolResult(isError=true)` so the LLM can react.
 */
final class ReadTool implements Tool
{
    private const int DEFAULT_LIMIT_LINES = 2000;
    private const int MAX_LIMIT_LINES = 2000;

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('read'),
            'Read a file from the project root. Returns content in `cat -n` numbered form. '
                .'Use offset/limit (line numbers, 0-indexed offset) to page through large files.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'filePath' => [
                        'type' => 'string',
                        'description' => 'File path relative to the project root.',
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'description' => '0-based line offset (default 0).',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => self::MAX_LIMIT_LINES,
                        'description' => 'Max number of lines to return (default '.self::DEFAULT_LIMIT_LINES.').',
                    ],
                ],
                'required' => ['filePath'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $filePath = $call->arguments['filePath'] ?? null;
        if (!\is_string($filePath) || '' === $filePath) {
            throw InvalidToolArguments::for($call->name, 'argument "filePath" must be a non-empty string');
        }
        $offset = $call->arguments['offset'] ?? 0;
        $limit = $call->arguments['limit'] ?? self::DEFAULT_LIMIT_LINES;
        if (!\is_int($offset) || $offset < 0) {
            throw InvalidToolArguments::for($call->name, 'argument "offset" must be a non-negative integer');
        }
        if (!\is_int($limit) || $limit < 1 || $limit > self::MAX_LIMIT_LINES) {
            throw InvalidToolArguments::for($call->name, \sprintf('argument "limit" must be an integer in [1, %d]', self::MAX_LIMIT_LINES));
        }

        $root = realpath($context->projectRoot);
        if (false === $root) {
            return ToolResult::failure($call->id, 'project root does not exist on disk');
        }

        $absolute = realpath($root.\DIRECTORY_SEPARATOR.ltrim($filePath, '/\\'));
        if (false === $absolute) {
            return ToolResult::failure($call->id, \sprintf('file "%s" not found', $filePath));
        }
        if (!str_starts_with($absolute, $root.\DIRECTORY_SEPARATOR) && $absolute !== $root) {
            return ToolResult::failure($call->id, \sprintf('path "%s" is outside the project root', $filePath));
        }
        if (!is_file($absolute)) {
            return ToolResult::failure($call->id, \sprintf('path "%s" is not a regular file', $filePath));
        }

        $handle = @fopen($absolute, 'r');
        if (false === $handle) {
            return ToolResult::failure($call->id, \sprintf('unable to open "%s" for reading', $filePath));
        }

        $lines = [];
        $bytes = 0;
        $lineNo = 0;
        $truncatedByBytes = false;
        try {
            while (false !== ($raw = fgets($handle))) {
                ++$lineNo;
                if ($lineNo <= $offset) {
                    continue;
                }
                if (\count($lines) >= $limit) {
                    break;
                }
                $bytes += \strlen($raw);
                if ($bytes > $context->maxOutputBytes) {
                    $truncatedByBytes = true;
                    break;
                }
                $lines[] = \sprintf('%6d  %s', $lineNo, rtrim($raw, "\r\n"));
            }
        } finally {
            fclose($handle);
        }

        $body = implode(\PHP_EOL, $lines);
        if ($truncatedByBytes) {
            $body .= \PHP_EOL.\sprintf('--- truncated at %d bytes ---', $context->maxOutputBytes);
        }

        return ToolResult::success(
            $call->id,
            $body,
            ['lines_returned' => \count($lines), 'truncated' => $truncatedByBytes],
        );
    }
}
