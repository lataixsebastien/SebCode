<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\Diagnostic;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\DiagnosticsProvider;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Report diagnostics (errors/warnings) for a file under the project root.
 *
 * Read-only and sandboxed by realpath() like read/glob/grep. The diagnostics
 * come from a {@see DiagnosticsProvider} (php -l + phpstan today). Useful for
 * the agent to check its own edits before declaring a task done.
 */
final readonly class LspTool implements Tool
{
    public function __construct(private DiagnosticsProvider $provider)
    {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('lsp'),
            'Report diagnostics (syntax errors and static-analysis problems) for a single PHP file under '
                .'the project root. Use it to check a file you just wrote or edited before saying the task '
                .'is done.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'filePath' => [
                        'type' => 'string',
                        'description' => 'File path relative to the project root.',
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

        $relative = ltrim(substr($absolute, \strlen($root)), '/\\');
        $diagnostics = $this->provider->diagnostics($absolute);

        return ToolResult::success(
            $call->id,
            $this->render($relative, $diagnostics),
            ['problems' => \count($diagnostics)],
        );
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private function render(string $relative, array $diagnostics): string
    {
        if ([] === $diagnostics) {
            return \sprintf('No problems found in %s.', $relative);
        }

        $lines = [\sprintf('Found %d problem(s) in %s:', \count($diagnostics), $relative)];
        foreach ($diagnostics as $d) {
            $lines[] = \sprintf('  [%s] line %d: %s [%s]', $d->severity->value, $d->line, $d->message, $d->source);
        }

        return implode("\n", $lines);
    }
}
