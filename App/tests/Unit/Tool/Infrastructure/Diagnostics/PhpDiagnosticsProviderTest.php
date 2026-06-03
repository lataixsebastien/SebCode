<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Diagnostics;

use App\Tests\Support\Tool\Doubles\FakeCommandRunner;
use App\Tool\Domain\Model\CommandResult;
use App\Tool\Domain\Model\ValueObject\DiagnosticSeverity;
use App\Tool\Infrastructure\Diagnostics\PhpDiagnosticsProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpDiagnosticsProvider::class)]
final class PhpDiagnosticsProviderTest extends TestCase
{
    public function testParsesASyntaxErrorAndSkipsPhpstan(): void
    {
        $runner = new FakeCommandRunner(new CommandResult(
            "PHP Parse error:  syntax error, unexpected token in /app/a.php on line 5\nErrors parsing /app/a.php",
            '',
            255,
        ));

        $diagnostics = (new PhpDiagnosticsProvider($runner, '/app'))->diagnostics('/app/a.php');

        self::assertCount(1, $diagnostics);
        self::assertSame(DiagnosticSeverity::Error, $diagnostics[0]->severity);
        self::assertSame(5, $diagnostics[0]->line);
        self::assertSame('php', $diagnostics[0]->source);
        self::assertCount(1, $runner->calls, 'phpstan must be skipped when the file does not parse.');
    }

    public function testParsesPhpstanMessagesForACleanlyParsingFile(): void
    {
        $json = '{"totals":{"file_errors":1},"files":{"/app/a.php":{"errors":1,"messages":[{"message":"Undefined variable $x","line":12,"ignorable":true}]}},"errors":[]}';
        $runner = new FakeCommandRunner(new CommandResult($json, '', 0));

        $diagnostics = (new PhpDiagnosticsProvider($runner, '/app'))->diagnostics('/app/a.php');

        self::assertCount(1, $diagnostics);
        self::assertSame(12, $diagnostics[0]->line);
        self::assertSame('Undefined variable $x', $diagnostics[0]->message);
        self::assertSame('phpstan', $diagnostics[0]->source);
        self::assertCount(2, $runner->calls, 'both php -l and phpstan run for a parsing file.');
    }

    public function testCleanFileYieldsNoDiagnostics(): void
    {
        $runner = new FakeCommandRunner(new CommandResult('{"totals":{"file_errors":0},"files":{},"errors":[]}', '', 0));

        self::assertSame([], (new PhpDiagnosticsProvider($runner, '/app'))->diagnostics('/app/a.php'));
    }
}
