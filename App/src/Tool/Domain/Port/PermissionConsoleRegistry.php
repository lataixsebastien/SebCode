<?php

declare(strict_types=1);

namespace App\Tool\Domain\Port;

/**
 * Holds the {@see PermissionConsole} currently wired to a live UI.
 *
 * A UI entry point (CLI command, later the TUI) attaches its console for the
 * duration of a run, then detaches it. The prompter reads {@see current()} at
 * the moment it needs an answer. This indirection lets the UI inject a
 * Symfony-bound console without the Tool layer (or the prompter) depending on
 * Symfony — and keeps the rule "UI never touches Infrastructure directly":
 * the UI only sees this Domain port.
 */
interface PermissionConsoleRegistry
{
    /**
     * The console to prompt through right now (an inactive one if none attached).
     */
    public function current(): PermissionConsole;

    public function attach(PermissionConsole $console): void;

    public function detach(): void;
}
