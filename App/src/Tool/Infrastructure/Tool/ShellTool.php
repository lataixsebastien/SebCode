<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Tool;

use App\Tool\Domain\Exception\InvalidToolArguments;
use App\Tool\Domain\Model\ToolCall;
use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Model\ToolResult;
use App\Tool\Domain\Model\ValueObject\JsonSchema;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Model\ValueObject\PermissionType;
use App\Tool\Domain\Model\ValueObject\ToolName;
use App\Tool\Domain\Port\CommandRunner;
use App\Tool\Domain\Port\PermissionGate;
use App\Tool\Domain\Port\Tool;
use App\Tool\Domain\Port\ToolExecutionContext;

/**
 * Run a shell command in the workspace.
 *
 * Mutating/side-effecting tool, gated through {@see PermissionGate} (type
 * `bash`) before anything runs. The full command is the subject pattern; the
 * remembered "always" pattern is the command's first token plus `*` (e.g.
 * `git *`) — a deliberately simplified port of opencode's tree-sitter + arity
 * extraction. A denied permission is a hard failure (aborts the loop); a
 * non-zero exit or a timeout is a soft failure fed back to the LLM.
 *
 * @see _opencode_ref/opencode-dev/packages/opencode/src/tool/shell.ts
 */
final readonly class ShellTool implements Tool
{
    private const int DEFAULT_TIMEOUT_MS = 120_000;
    private const int MAX_TIMEOUT_MS = 600_000;

    public function __construct(
        private PermissionGate $permission,
        private CommandRunner $runner,
    ) {
    }

    public function descriptor(): ToolDescriptor
    {
        return new ToolDescriptor(
            ToolName::of('bash'),
            'Run a shell command in the project root and return its combined output and exit code. '
                .'Use it for terminal tasks (git, composer, listing, running tests) — NOT for reading, '
                .'writing or editing files, which have dedicated tools. Commands require permission.',
            JsonSchema::of([
                'type' => 'object',
                'properties' => [
                    'command' => [
                        'type' => 'string',
                        'description' => 'The shell command to execute.',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'A short human-readable description of what the command does.',
                    ],
                    'timeout' => [
                        'type' => 'integer',
                        'description' => 'Optional timeout in milliseconds (default 120000, max 600000).',
                    ],
                ],
                'required' => ['command'],
            ]),
        );
    }

    public function execute(ToolCall $call, ToolExecutionContext $context): ToolResult
    {
        $command = $call->arguments['command'] ?? null;
        if (!\is_string($command) || '' === trim($command)) {
            throw InvalidToolArguments::for($call->name, 'argument "command" must be a non-empty string');
        }

        $description = $call->arguments['description'] ?? null;
        if (null !== $description && !\is_string($description)) {
            throw InvalidToolArguments::for($call->name, 'argument "description" must be a string');
        }

        $timeoutMs = $this->resolveTimeout($call);

        // Hard failure if denied — propagates and aborts the loop (opencode parity).
        $this->permission->ensure(new PermissionRequest(
            PermissionType::Bash,
            [trim($command)],
            ['command' => trim($command), 'description' => $description],
            [$this->alwaysPattern($command)],
        ));

        $result = $this->runner->run($command, $context->projectRoot, $timeoutMs);

        $body = $result->stdout;
        if ('' !== trim($result->stderr)) {
            $body .= ('' !== $body ? "\n" : '').$result->stderr;
        }
        $body = $this->tail($body, $context->maxOutputBytes);
        if ('' === trim($body)) {
            $body = '(no output)';
        }

        $status = $result->timedOut
            ? \sprintf('[command terminated: timeout after %d ms]', $timeoutMs)
            : \sprintf('[exit code: %s]', $result->exitCode ?? '?');

        return new ToolResult(
            $call->id,
            $body."\n\n".$status,
            $result->timedOut || (null !== $result->exitCode && 0 !== $result->exitCode),
            [
                'command' => trim($command),
                'description' => $description,
                'exitCode' => $result->exitCode,
                'timedOut' => $result->timedOut,
            ],
        );
    }

    private function resolveTimeout(ToolCall $call): int
    {
        $timeout = $call->arguments['timeout'] ?? null;
        if (null === $timeout) {
            return self::DEFAULT_TIMEOUT_MS;
        }
        if (!\is_int($timeout) || $timeout <= 0) {
            throw InvalidToolArguments::for($call->name, 'argument "timeout" must be a positive integer (milliseconds)');
        }

        return min($timeout, self::MAX_TIMEOUT_MS);
    }

    /**
     * Simplified "always" pattern: first token + " *" (e.g. `git *`).
     */
    private function alwaysPattern(string $command): string
    {
        preg_match('/^\s*(\S+)/', $command, $m);
        $first = $m[1] ?? '';

        return '' === $first ? trim($command) : $first.' *';
    }

    private function tail(string $output, int $maxBytes): string
    {
        if (\strlen($output) <= $maxBytes) {
            return $output;
        }

        return "...output truncated...\n".substr($output, -$maxBytes);
    }
}
