<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Diagnostics;

use App\Tool\Domain\Model\Diagnostic;
use App\Tool\Domain\Model\ValueObject\DiagnosticSeverity;
use App\Tool\Domain\Port\CommandRunner;
use App\Tool\Domain\Port\DiagnosticsProvider;

/**
 * PHP-native diagnostics: `php -l` for syntax errors and phpstan for static
 * analysis, run through the workspace {@see CommandRunner}.
 *
 * A pragmatic, dependency-free stand-in for a full LSP server: robust and
 * local, behind the {@see DiagnosticsProvider} port so a real language-server
 * backend can replace it later without touching the tool.
 */
final readonly class PhpDiagnosticsProvider implements DiagnosticsProvider
{
    private const int LINT_TIMEOUT_MS = 30_000;
    private const int STAN_TIMEOUT_MS = 120_000;

    public function __construct(
        private CommandRunner $runner,
        private string $projectRoot,
    ) {
    }

    public function diagnostics(string $absolutePath): array
    {
        $syntax = $this->lint($absolutePath);
        if ([] !== $syntax) {
            // A file that doesn't parse can't be statically analysed — stop here.
            return $syntax;
        }

        return $this->phpstan($absolutePath);
    }

    /**
     * @return list<Diagnostic>
     */
    private function lint(string $absolutePath): array
    {
        $result = $this->runner->run('php -l '.escapeshellarg($absolutePath), $this->projectRoot, self::LINT_TIMEOUT_MS);
        if (null === $result->exitCode || 0 === $result->exitCode) {
            return [];
        }

        $output = trim($result->stdout."\n".$result->stderr);
        $line = 1 === preg_match('/on line (\d+)/', $output, $m) ? (int) $m[1] : 0;
        $message = $this->firstMeaningfulLine($output);

        return [new Diagnostic(DiagnosticSeverity::Error, $line, $message, 'php')];
    }

    /**
     * @return list<Diagnostic>
     */
    private function phpstan(string $absolutePath): array
    {
        $result = $this->runner->run(
            'vendor/bin/phpstan analyse -c phpstan.dist.neon --no-progress --no-ansi --memory-limit=512M --error-format=json '.escapeshellarg($absolutePath),
            $this->projectRoot,
            self::STAN_TIMEOUT_MS,
        );

        $json = json_decode(trim($result->stdout), true);
        if (!\is_array($json) || !isset($json['files']) || !\is_array($json['files'])) {
            return [];
        }

        $diagnostics = [];
        foreach ($json['files'] as $file) {
            $messages = \is_array($file) && \is_array($file['messages'] ?? null) ? $file['messages'] : [];
            foreach ($messages as $message) {
                if (!\is_array($message)) {
                    continue;
                }
                $rawLine = $message['line'] ?? null;
                $line = (\is_int($rawLine) || (\is_string($rawLine) && is_numeric($rawLine))) ? (int) $rawLine : 0;
                $diagnostics[] = new Diagnostic(
                    DiagnosticSeverity::Error,
                    $line,
                    \is_string($message['message'] ?? null) ? $message['message'] : '',
                    'phpstan',
                );
            }
        }

        return $diagnostics;
    }

    private function firstMeaningfulLine(string $output): string
    {
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ('' !== $line) {
                return $line;
            }
        }

        return 'syntax error';
    }
}
