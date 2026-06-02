<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model;

/**
 * Outcome of running a shell command: captured streams, exit status, and
 * whether the runner had to kill it for exceeding its timeout.
 *
 * `exitCode` is null when the process was terminated (timeout/signal) before
 * reporting a status — mirroring opencode's `exit: code` being null on abort.
 */
final readonly class CommandResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
        public bool $timedOut = false,
    ) {
    }
}
