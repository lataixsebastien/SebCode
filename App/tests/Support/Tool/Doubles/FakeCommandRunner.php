<?php

declare(strict_types=1);

namespace App\Tests\Support\Tool\Doubles;

use App\Tool\Domain\Model\CommandResult;
use App\Tool\Domain\Port\CommandRunner;

/**
 * Returns a scripted {@see CommandResult} and records every invocation, so the
 * shell tool can be unit-tested without spawning a real process.
 */
final class FakeCommandRunner implements CommandRunner
{
    /**
     * @var list<array{command: string, workdir: string, timeoutMs: int}>
     */
    public array $calls = [];

    public function __construct(private readonly CommandResult $result)
    {
    }

    public function run(string $command, string $workdir, int $timeoutMs): CommandResult
    {
        $this->calls[] = ['command' => $command, 'workdir' => $workdir, 'timeoutMs' => $timeoutMs];

        return $this->result;
    }
}
