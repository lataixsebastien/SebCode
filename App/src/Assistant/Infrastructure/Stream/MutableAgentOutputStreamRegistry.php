<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Stream;

use App\Assistant\Domain\Port\AgentOutputStream;
use App\Assistant\Domain\Port\AgentOutputStreamRegistry;

/**
 * Process-wide holder for the live {@see AgentOutputStream}. Shared singleton:
 * the CLI/TUI command attaches its sink before running the loop (same call
 * stack) and detaches in a finally. Starts (and resets) to a no-op sink.
 */
final class MutableAgentOutputStreamRegistry implements AgentOutputStreamRegistry
{
    private AgentOutputStream $stream;

    public function __construct(private readonly NullAgentOutputStream $none)
    {
        $this->stream = $none;
    }

    public function current(): AgentOutputStream
    {
        return $this->stream;
    }

    public function attach(AgentOutputStream $stream): void
    {
        $this->stream = $stream;
    }

    public function detach(): void
    {
        $this->stream = $this->none;
    }
}
