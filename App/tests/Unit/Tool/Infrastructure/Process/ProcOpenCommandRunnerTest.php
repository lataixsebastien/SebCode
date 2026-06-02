<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tool\Infrastructure\Process;

use App\Tool\Infrastructure\Process\ProcOpenCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real proc_open adapter. POSIX-only: the assertions use `/bin/sh`
 * syntax, so the suite skips on a Windows host (CI runs in the Linux container).
 */
#[CoversClass(ProcOpenCommandRunner::class)]
final class ProcOpenCommandRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped('POSIX shell assertions; runs in the Linux container.');
        }
    }

    public function testCapturesStdoutAndZeroExit(): void
    {
        $result = (new ProcOpenCommandRunner())->run('echo hi', sys_get_temp_dir(), 5000);

        self::assertSame("hi\n", $result->stdout);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertFalse($result->timedOut);
    }

    public function testReportsNonZeroExitCode(): void
    {
        $result = (new ProcOpenCommandRunner())->run('exit 3', sys_get_temp_dir(), 5000);

        self::assertSame(3, $result->exitCode);
        self::assertFalse($result->timedOut);
    }

    public function testCapturesStderrSeparately(): void
    {
        $result = (new ProcOpenCommandRunner())->run('echo oops 1>&2', sys_get_temp_dir(), 5000);

        self::assertSame('', $result->stdout);
        self::assertSame("oops\n", $result->stderr);
    }

    public function testRunsInTheGivenWorkdir(): void
    {
        $dir = (string) realpath(sys_get_temp_dir());
        $result = (new ProcOpenCommandRunner())->run('pwd', $dir, 5000);

        self::assertSame($dir, trim($result->stdout));
    }

    public function testKillsAndFlagsOnTimeout(): void
    {
        $result = (new ProcOpenCommandRunner())->run('sleep 5', sys_get_temp_dir(), 200);

        self::assertTrue($result->timedOut);
        self::assertNull($result->exitCode);
    }
}
