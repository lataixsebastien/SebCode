<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

use App\Tool\Domain\Model\CommandResult;

/**
 * Runs a shell command and returns its captured output.
 *
 * Abstracts process spawning out of {@see \App\Tool\Infrastructure\Tool\ShellTool}
 * so the tool's logic (validation, permission, formatting, truncation) stays
 * unit-testable with a fake, and the platform-specific `proc_open` plumbing
 * lives behind one adapter.
 */
interface CommandRunner
{
    /**
     * @param string $command the command line, run through a shell
     * @param string $workdir absolute working directory
     * @param int $timeoutMs hard wall-clock budget; the process is killed past it
     */
    public function run(string $command, string $workdir, int $timeoutMs): CommandResult;
}
