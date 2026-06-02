<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Process;

use App\Tool\Domain\Model\CommandResult;
use App\Tool\Domain\Port\CommandRunner;

/**
 * Runs commands through the system shell with `proc_open`, capturing stdout
 * and stderr separately and enforcing a wall-clock timeout.
 *
 * The command string is handed to a shell via `-c` (array form, so PHP itself
 * does not re-parse it). On timeout the process gets SIGTERM, then SIGKILL
 * after a short grace — paralleling opencode's graceful-then-force kill.
 *
 * Targets the POSIX shell of the Linux runtime container; Windows falls back
 * to `cmd /c` for local runs.
 */
final readonly class ProcOpenCommandRunner implements CommandRunner
{
    private const int KILL_GRACE_US = 500_000; // 0.5s between SIGTERM and SIGKILL
    private const int POLL_TIMEOUT_US = 100_000; // stream_select tick

    public function run(string $command, string $workdir, int $timeoutMs): CommandResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($this->shellInvocation($command), $descriptors, $pipes, $workdir);
        if (!\is_resource($process)) {
            return new CommandResult('', \sprintf('could not start command in %s', $workdir), null);
        }

        fclose($pipes[0]); // stdin: ignored, like opencode's stdin: "ignore"
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $exitCode = null;
        $timedOut = false;
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (true) {
            $status = proc_get_status($process);

            $stdout .= $this->drain($pipes[1]);
            $stderr .= $this->drain($pipes[2]);

            if (!$status['running']) {
                $exitCode = -1 === $status['exitcode'] ? null : $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                $this->kill($process);
                $stdout .= $this->drain($pipes[1]);
                $stderr .= $this->drain($pipes[2]);
                break;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, self::POLL_TIMEOUT_US);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return new CommandResult($stdout, $stderr, $exitCode, $timedOut);
    }

    /**
     * @return list<string>
     */
    private function shellInvocation(string $command): array
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            return ['cmd', '/c', $command];
        }

        return ['/bin/sh', '-c', $command];
    }

    /**
     * @param resource $pipe
     */
    private function drain($pipe): string
    {
        $chunk = stream_get_contents($pipe);

        return \is_string($chunk) ? $chunk : '';
    }

    /**
     * @param resource $process
     */
    private function kill($process): void
    {
        proc_terminate($process); // SIGTERM
        $until = microtime(true) + (self::KILL_GRACE_US / 1_000_000);
        while (microtime(true) < $until) {
            if (!proc_get_status($process)['running']) {
                return;
            }
            usleep(10_000);
        }
        proc_terminate($process, 9); // SIGKILL
    }
}
