<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Port;

/**
 * Holds the {@see AgentOutputStream} a live UI has attached for the duration of
 * a request. The agent loop reads {@see current()} to report progress; the UI
 * attaches before invoking and detaches afterwards. Same indirection as the
 * permission console registry, so the Application loop stays UI-agnostic.
 */
interface AgentOutputStreamRegistry
{
    public function current(): AgentOutputStream;

    public function attach(AgentOutputStream $stream): void;

    public function detach(): void;
}
